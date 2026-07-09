<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Exceptions\InvalidScopeDefinitionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * An Eloquent global scope that integrates a contextual scope into Eloquent's query pipeline.
 *
 * One wrapper is created per registered scope key and installed as a named Eloquent global scope
 * on the model. At query build time, the wrapper:
 *
 *  1. Checks whether the scope has been disabled via the active {@see ModelScopeContext}.
 *  2. If disabled, evaluates the disabling guard. When the guard returns true (disabling is
 *     allowed), the scope is skipped. When it returns false, the "not-allowed" callback is
 *     invoked — which typically aborts with 403.
 *  3. If not disabled (or guard allows it), resolves the inner {@see Scope} instance lazily
 *     (class name → service locator, Closure → container call, Scope instance → used directly)
 *     and delegates to its {@see Scope::apply()} method.
 *
 * Trait initialiser methods on the inner scope are also called automatically after resolution,
 * allowing the inner scope to use service-injected traits.
 */
class ContextualScopeWrapper implements Scope
{
    private Scope|null $innerScope = null;

    public function __construct(
        private readonly string            $scopeKey,
        private readonly ScopeRegistrar    $registrar,
        private readonly ModelScopeContext $context,
        private readonly ServiceLocator    $serviceLocator
    )
    {
    }

    /**
     * Returns the short scope key as registered in the {@see ScopeRegistrar}.
     */
    public function getScopeKey(): string
    {
        return $this->scopeKey;
    }

    /**
     * Returns the full Eloquent global scope registration key for this wrapper.
     */
    public function getFullScopeKey(): string
    {
        return $this->context->getFullScopeKey($this->scopeKey);
    }

    /**
     * Returns the {@see ModelScopeContext} this wrapper operates within.
     */
    public function getContext(): ModelScopeContext
    {
        return $this->context;
    }

    /**
     * Convenience method to disable this scope in the current context.
     * Equivalent to calling {@see ModelScopeContext::disableScope()} with this scope's key.
     */
    public function disable(\Closure|null $onNotAllowed = null): void
    {
        $this->context->disableScope($this->scopeKey, $onNotAllowed);
    }

    /**
     * Evaluates whether this scope should be skipped for the current query.
     *
     * Returns true when the scope is disabled AND the disabling guard permits it.
     * Returns false when the scope is not disabled or when the guard returns false
     * (in which case the "not-allowed" callback decides what happens next).
     */
    public function evaluateDisabling(): bool
    {
        if ($this->context->isScopeDisabled($this->scopeKey)) {
            $guard = $this->registrar->getDisablingGuard($this->scopeKey);
            $isAllowedToDisable = $this->serviceLocator->call(
                ['scopeWrapper.evaluate.disabling.guard', $this->scopeKey],
                $guard
            );

            if ($isAllowedToDisable) {
                return true;
            }

            $onNotAllowed = $this->context->getOnDisableNotAllowed($this->scopeKey);
            return (bool)($onNotAllowed)($this->scopeKey, $this->context);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($this->evaluateDisabling()) {
            return;
        }

        $innerScope = $this->innerScope ??= $this->makeInnerScopeFromDefinition();

        $innerScope->apply($builder, $model);
    }

    private function makeInnerScopeFromDefinition(): Scope
    {
        $scope = (function () {
            $definition = $this->registrar->getScopeDefinition($this->scopeKey);
            if ($definition === null) {
                throw InvalidScopeDefinitionException::forMissingDefinition($this->scopeKey, $this->registrar->modelClass);
            }
            if ($definition instanceof Scope) {
                return $definition;
            }
            if ($definition instanceof \Closure) {
                return $this->serviceLocator->call(
                    ['innerScope.closure', $this->scopeKey],
                    $definition
                );
            }
            if (is_string($definition)) {
                return $this->serviceLocator->get($definition);
            }
            // @phpstan-ignore-next-line deadCode.unreachable
            throw InvalidScopeDefinitionException::forInvalidDefinitionType($this->scopeKey, $this->registrar->modelClass);
        })();

        if (!$scope instanceof Scope) {
            throw InvalidScopeDefinitionException::forInvalidResolvedValue($this->scopeKey, $this->registrar->modelClass, $scope);
        }

        // Initialize the traits if needed
        foreach (class_uses_recursive($scope) as $trait) {
            $initializerMethod = 'initialize' . class_basename($trait);
            if (is_callable([$scope, $initializerMethod])) {
                $this->serviceLocator->call(
                    ['scopeWrapper.initializeTrait', $trait, $this->scopeKey],
                    [$scope, $initializerMethod]
                );
            }
        }

        return $scope;
    }
}
