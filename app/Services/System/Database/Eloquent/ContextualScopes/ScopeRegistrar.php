<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use Illuminate\Database\Eloquent\Scope;
use Traversable;

/**
 * @implements \IteratorAggregate<string>
 */
class ScopeRegistrar implements \IteratorAggregate
{
    private readonly \Closure $originalDefaultDisablingGuard;
    private array $scopeDefinitions = [];
    private array $disablingGuards = [];

    public function __construct(
        public readonly string $modelClass,
        private \Closure       $defaultDisablingGuard
    )
    {
        $this->originalDefaultDisablingGuard = $defaultDisablingGuard;
    }

    public function setDefaultDisablingGuard(\Closure $guard): self
    {
        $this->defaultDisablingGuard = $guard;
        return $this;
    }

    public function resetDefaultDisablingGuard(): self
    {
        $this->defaultDisablingGuard = $this->originalDefaultDisablingGuard;
        return $this;
    }

    public function addScope(
        string                $scopeKey,
        string|Scope|\Closure $scope,
        \Closure|null         $disablingGuard = null
    ): self
    {
        $this->scopeDefinitions[$scopeKey] = $scope;
        if ($disablingGuard !== null) {
            $this->disablingGuards[$scopeKey] = $disablingGuard;
        }
        return $this;
    }

    public function hasScope(string $scopeKey): bool
    {
        return isset($this->scopeDefinitions[$scopeKey]);
    }

    public function removeScope(string $scopeKey): self
    {
        unset($this->scopeDefinitions[$scopeKey], $this->disablingGuards[$scopeKey]);
        return $this;
    }

    public function getScopeDefinition(string $scopeKey): string|Scope|\Closure|null
    {
        return $this->scopeDefinitions[$scopeKey] ?? null;
    }

    public function getDisablingGuard(string $scopeKey): \Closure
    {
        return $this->disablingGuards[$scopeKey] ?? $this->defaultDisablingGuard;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator(array_keys($this->scopeDefinitions));
    }
}
