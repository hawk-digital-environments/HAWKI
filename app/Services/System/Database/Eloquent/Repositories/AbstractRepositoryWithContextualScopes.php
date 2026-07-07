<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories;


use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * Extends {@see AbstractRepository} with support for contextual scopes.
 *
 * Use this base class when the associated model uses {@see HasContextualScopesTrait}.
 * All query methods accept an optional {@see ScopeOverrides} argument that lets callers
 * selectively disable contextual scopes for a single query without touching global state.
 *
 * Scopes are disabled by key (or all at once) and can carry a "not-allowed" callback
 * that is invoked if the scope refuses to be bypassed (e.g., for security enforcement).
 *
 * Usage:
 * ```php
 * class AiModelRepository extends AbstractRepositoryWithContextualScopes
 * {
 *     // Omit ScopeOverrides to query with all active scopes applied:
 *     public function findActive(): Collection
 *     {
 *         return $this->findAll();
 *     }
 *
 *     // Pass ScopeOverrides to bypass a specific scope (e.g., admin sees inactive too):
 *     public function findAllForAdmin(): Collection
 *     {
 *         return $this->findAll($this->makeScopeOverrides(ScopeKeys::ACTIVE_FILTER));
 *     }
 * }
 * ```
 *
 * @template TModel of Model
 * @extends AbstractRepository<TModel>
 */
abstract class AbstractRepositoryWithContextualScopes extends AbstractRepository
{
    use MakesDisableNotAllowedCallbacksTrait;

    private bool $hasCheckedContextualScopesTrait = false;

    /**
     * Convenience factory for building a {@see ScopeOverrides} value object.
     *
     * @param true|array|string|null $disableScopes Scope keys to disable, `true` to disable all,
     *                                               or `null` to return an empty (no-op) overrides.
     * @param \Closure|true|null $onNotAllowed Callback invoked when the scope refuses bypassing.
     *                                          Pass `true` to use the built-in "force disable" callback
     *                                          which ignores the refusal and disables the scope anyway.
     */
    public function makeScopeOverrides(
        true|array|string|null $disableScopes = true,
        \Closure|true|null     $onNotAllowed = null
    ): ScopeOverrides
    {
        $overrides = new ScopeOverrides();
        if ($disableScopes === null) {
            return $overrides;
        }

        $onNotAllowed = $onNotAllowed === true
            ? $this->makeDisableNotAllowedForceDisable()
            : $onNotAllowed;

        if ($disableScopes === true) {
            $overrides->withAllDisabled($onNotAllowed);
        } else {
            $overrides->withDisabled($disableScopes, $onNotAllowed);
        }

        return $overrides;
    }

    /**
     * Finds a single record by primary key, optionally bypassing contextual scopes.
     *
     * @return TModel|null
     */
    public function findOne(mixed $id, ?ScopeOverrides $scopeOverrides = null): ?Model
    {
        return $this->getQuery($scopeOverrides)->find($id);
    }

    /**
     * Retrieves all records as an Eloquent Collection, optionally bypassing contextual scopes.
     *
     * @return Collection<int, TModel>
     */
    public function findAll(?ScopeOverrides $scopeOverrides = null): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->getQuery($scopeOverrides)->get();
    }

    /**
     * Retrieves all records as a LazyCollection, optionally bypassing contextual scopes.
     * Evaluates rows in chunks to keep memory usage constant on large tables.
     *
     * @return LazyCollection<int, TModel>
     */
    public function findAllLazy(?ScopeOverrides $scopeOverrides = null): LazyCollection
    {
        return $this->getQuery($scopeOverrides)->lazy();
    }

    /**
     * Returns a query builder within a sandboxed scope context configured by the given closure.
     * Use when you need fine-grained control over context values beyond what {@see ScopeOverrides} provides.
     *
     * @param \Closure(ModelScopeContext): void $contextConfigurator
     * @return Builder<TModel>
     */
    protected function getQueryWithScopeContext(\Closure $contextConfigurator): Builder
    {
        return $this->getQuery((new ScopeOverrides())->withContextConfigurator($contextConfigurator));
    }

    /**
     * Returns a query builder with specific (or all) contextual scopes force-disabled.
     *
     * Unlike {@see getQueryWithoutAnyScopes()}, this only affects contextual scopes — Eloquent
     * global scopes remain active. Pass null to disable every contextual scope on the model.
     *
     * The disabling is unconditional: any "not-allowed" callbacks registered on the scopes are
     * bypassed. Use with care — prefer {@see makeScopeOverrides()} when scope-level validation matters.
     *
     * @return Builder<TModel>
     */
    protected function getQueryWithoutContextualScopes(array|string|null $scopesToDisable = null): Builder
    {
        $overrides = $scopesToDisable === null
            ? ScopeOverrides::makeWithAllForcefullyDisabled()
            : ScopeOverrides::makeWithForcefullyDisabled($scopesToDisable);
        return $this->getQuery($overrides);
    }

    /**
     * Returns a query builder for the model, applying the given scope overrides in a sandboxed context.
     * When no overrides are given, delegates to the parent implementation (all scopes active).
     *
     * @return Builder<TModel>
     */
    #[\Override]
    protected function getQuery(?ScopeOverrides $scopeOverrides = null): Builder
    {
        /** @see Model */
        $modelClass = $this->getModelClass();
        $this->assertModelHasContextualScopesTrait($modelClass);

        if ($scopeOverrides === null) {
            return parent::getQuery();
        }

        /** @phpstan-ignore staticMethod.notFound (scopeContext() comes from HasContextualScopesTrait, which PHPStan can't intersect with TModel) */
        return $modelClass::scopeContext()->runSandboxed(
            function (ModelScopeContext $context) use ($scopeOverrides, $modelClass) {
                $scopeOverrides->apply($context);

                // Because the scopes are applied later (when the query is actually being built)
                // We must first create the builder and manually disable our scopes on an eloquent level,
                // because once the builder would evaluate them, the context is already reset and the scopes would be applied anyway.

                $query = parent::getQuery();
                /** @phpstan-ignore staticMethod.notFound (getContextualScopes() comes from HasContextualScopesTrait, which PHPStan can't intersect with TModel) */
                foreach ($modelClass::getContextualScopes() as $scope) {
                    if ($scope->evaluateDisabling()) {
                        $query->withoutGlobalScope($scope->getFullScopeKey());
                    }
                }

                return $query;
            });
    }

    private function assertModelHasContextualScopesTrait(string $modelClass): void
    {
        if ($this->hasCheckedContextualScopesTrait) {
            return;
        }

        if (!in_array(HasContextualScopesTrait::class, class_uses_recursive($modelClass))) {
            throw new \LogicException(sprintf(
                'Model class "%s" does not use %s. This method is only for models with context aware scopes.',
                $modelClass,
                HasContextualScopesTrait::class
            ));
        }

        $this->hasCheckedContextualScopesTrait = true;
    }
}
