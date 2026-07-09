<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use Illuminate\Database\Eloquent\Scope;
use Traversable;

/**
 * Collects scope definitions for a model during the boot phase of {@see HasContextualScopesTrait}.
 *
 * Passed to {@see HasContextualScopesTrait::registerScopes()} so models can declare which
 * contextual scopes they want applied. Each scope is registered by a short key string paired with:
 *  - A scope definition: a {@see Scope} instance, a class-name string (resolved lazily from the
 *    service container), or a Closure (called by the service container for DI).
 *  - An optional disabling guard: a callable that returns true when the current caller IS permitted
 *    to bypass this scope. Scopes without an explicit guard inherit the global default from
 *    {@see \App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext}.
 *
 * After all scopes are registered, iterating over the registrar yields the scope key strings,
 * which the trait uses to wrap each scope in a {@see ContextualScopeWrapper} and register it
 * as an Eloquent global scope on the model.
 *
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

    /**
     * Replaces the default disabling guard for all subsequently added scopes that do not
     * declare an explicit guard. Useful when a model needs a different base permission check.
     */
    public function setDefaultDisablingGuard(\Closure $guard): self
    {
        $this->defaultDisablingGuard = $guard;
        return $this;
    }

    /**
     * Restores the default disabling guard to the one provided at construction time.
     */
    public function resetDefaultDisablingGuard(): self
    {
        $this->defaultDisablingGuard = $this->originalDefaultDisablingGuard;
        return $this;
    }

    /**
     * Registers a contextual scope under the given key.
     *
     * @param string $scopeKey Short identifier used to reference this scope (e.g. `'active_filter'`).
     * @param string|Scope|\Closure $scope Class name (resolved via the service locator), a pre-built Scope instance,
     *                                      or a Closure invoked via container call for dependency injection.
     * @param \Closure|null $disablingGuard Optional guard returning true when the scope MAY be disabled.
     *                                       Falls back to the default guard when null.
     */
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

    /**
     * Returns true when a scope has been registered under the given key.
     */
    public function hasScope(string $scopeKey): bool
    {
        return isset($this->scopeDefinitions[$scopeKey]);
    }

    /**
     * Removes a previously registered scope and its guard by key.
     */
    public function removeScope(string $scopeKey): self
    {
        unset($this->scopeDefinitions[$scopeKey], $this->disablingGuards[$scopeKey]);
        return $this;
    }

    /**
     * Returns the raw scope definition (class name, Scope instance, or Closure) for the given key,
     * or null when no scope has been registered under that key.
     */
    public function getScopeDefinition(string $scopeKey): string|Scope|\Closure|null
    {
        return $this->scopeDefinitions[$scopeKey] ?? null;
    }

    /**
     * Returns the disabling guard for the given scope key.
     * Falls back to the current default guard when no scope-specific guard was registered.
     */
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
