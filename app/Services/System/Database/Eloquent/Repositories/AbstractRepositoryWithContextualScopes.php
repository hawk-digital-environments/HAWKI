<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories;


use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @template TModel of Model
 * @extends AbstractRepository<TModel>
 */
abstract class AbstractRepositoryWithContextualScopes extends AbstractRepository
{
    use MakesDisableNotAllowedCallbacksTrait;

    private bool $hasCheckedContextualScopesTrait = false;

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
     * @return TModel|null
     */
    public function findOne(mixed $id, ?ScopeOverrides $scopeOverrides = null): ?Model
    {
        return $this->getQuery($scopeOverrides)->find($id);
    }

    /**
     * @return Collection<int, TModel>
     */
    public function findAll(?ScopeOverrides $scopeOverrides = null): Collection
    {
        return $this->getQuery($scopeOverrides)->get();
    }

    /**
     * @return LazyCollection<int, TModel>
     */
    public function findAllLazy(?ScopeOverrides $scopeOverrides = null): LazyCollection
    {
        return $this->getQuery($scopeOverrides)->lazy();
    }

    /**
     * @param \Closure(ModelScopeContext): void $contextConfigurator
     * @return Builder<TModel>
     */
    protected function getQueryWithScopeContext(\Closure $contextConfigurator): Builder
    {
        return $this->getQuery((new ScopeOverrides())->withContextConfigurator($contextConfigurator));
    }

    /**
     * Note: This affects only contextual scopes, global scopes are not affected by this method. {@see self::getQueryWithoutAnyScopes()}
     * Note2: This does forcefully disable the scopes, so there is no user based validation if the disabling is allowed or not, use with care.
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
     * @param ScopeOverrides|null $scopeOverrides
     * @return Builder<TModel>
     */
    #[\Override]
    protected function getQuery(?ScopeOverrides $scopeOverrides = null): Builder
    {
        /** @var class-string<HasContextualScopesTrait & Model> $modelClass */
        $modelClass = $this->getModelClass();
        $this->assertModelHasContextualScopesTrait($modelClass);

        if ($scopeOverrides === null) {
            return parent::getQuery();
        }

        return $modelClass::scopeContext()->runSandboxed(
            function (ModelScopeContext $context) use ($scopeOverrides, $modelClass) {
                $scopeOverrides->apply($context);

                // Because the scopes are applied later (when the query is actually being built)
                // We must first create the builder and manually disable our scopes on an eloquent level,
                // because once the builder would evaluate them, the context is already reset and the scopes would be applied anyway.

                $query = parent::getQuery();
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
