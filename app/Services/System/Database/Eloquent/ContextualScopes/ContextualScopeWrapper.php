<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Exceptions\InvalidScopeDefinitionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

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

    public function getScopeKey(): string
    {
        return $this->scopeKey;
    }

    public function getFullScopeKey(): string
    {
        return $this->context->getFullScopeKey($this->scopeKey);
    }

    public function getContext(): ModelScopeContext
    {
        return $this->context;
    }

    public function disable(\Closure|null $onNotAllowed = null): void
    {
        $this->context->disableScope($this->scopeKey, $onNotAllowed);
    }

    // Return true -> disable, false -> apply the scope as normal, exception -> handle as not allowed to disable
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
