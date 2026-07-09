<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use Illuminate\Container\Attributes\Singleton;

/**
 * Global singleton that holds the state for all contextual scopes across every model.
 *
 * This is the top-level control point for the contextual scope system. It stores:
 *  - A default "is disabling allowed" guard: a callable that answers whether the current caller
 *    is permitted to bypass scopes (e.g. check if the user is an admin).
 *  - A default "not-allowed" callback: invoked when a scope refuses to be disabled.
 *    Defaults to aborting with 403.
 *  - A global "disable all scopes" flag that bypasses every scope on every model.
 *  - Per-model {@see ModelScopeContext} instances holding model-specific scope state.
 *
 * The key operation is {@see runSandboxed()}: it snapshots the current state, runs a closure,
 * and restores the previous state when the closure returns — even on exception. Repositories
 * use this to temporarily override scope state for a single query without affecting concurrent
 * requests or later queries in the same process.
 *
 * @see \App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait  Wires models into this system.
 * @see ModelScopeContext  Per-model sub-context managed by this class.
 */
#[Singleton]
class ScopeContext
{
    use MakesDisableNotAllowedCallbacksTrait;

    private ScopeContextState $globalState;
    /** @var array<string, ModelScopeContext> */
    private array $modelContexts = [];

    public function __construct(\Closure $defaultIsDisablingAllowedGuard)
    {
        $this->globalState = new ScopeContextState(
            $defaultIsDisablingAllowedGuard,
            $this->makeDisableNotAllowedThrowException()
        );
    }

    /**
     * Replaces the default guard that decides whether a scope is allowed to be disabled.
     * The guard is a closure that returns true when disabling is permitted, false otherwise.
     */
    public function setDefaultIsDisablingAllowedGuard(\Closure $guard): self
    {
        $this->globalState->defaultIsDisablingAllowedGuard = $guard;
        return $this;
    }

    /**
     * Returns the currently active default disabling guard.
     */
    public function getDefaultIsDisablingAllowedGuard(): \Closure
    {
        return $this->globalState->defaultIsDisablingAllowedGuard;
    }

    /**
     * Replaces the default callback invoked when a scope's guard refuses bypassing.
     * See {@see MakesDisableNotAllowedCallbacksTrait} for pre-built callbacks.
     */
    public function setDefaultOnDisabledNotAllowed(\Closure $onNotAllowed): self
    {
        $this->globalState->defaultOnDisableNotAllowed = $onNotAllowed;
        return $this;
    }

    /**
     * Globally disables every contextual scope on every model for the current process.
     *
     * Use sparingly — intended for admin tools or CLI operations that must see all records
     * without any scope filtering. Pass a "not-allowed" callback to control how scopes
     * that refuse bypassing are handled while the flag is active.
     */
    public function setAllScopesGloballyDisabled(bool $disabled = true, \Closure|null $onNotAllowed = null): self
    {
        $this->globalState->allScopesGloballyDisabled = $disabled;
        $this->globalState->onAllScopesGloballyDisabledNotAllowed = $disabled ? $onNotAllowed : null;
        return $this;
    }

    /**
     * Returns (or lazily creates) the {@see ModelScopeContext} for the given model class.
     * The same instance is returned on every call for the same class.
     */
    public function getModelContext(string $modelClass): ModelScopeContext
    {
        return $this->modelContexts[$modelClass] ??= new ModelScopeContext(
            modelClass: $modelClass,
            sandBoxRunner: $this->runSandboxed(...),
            globalState: $this->globalState
        );
    }

    /**
     * Runs a closure in a sandboxed scope context.
     *
     * The current global state is cloned before the closure executes and restored
     * when it returns or throws. The closure receives this {@see ScopeContext} instance
     * as its only argument.
     */
    public function runSandboxed(
        \Closure $callback
    ): mixed
    {
        $stateBackup = $this->globalState->clone();

        try {
            return $callback($this);
        } finally {
            $this->globalState->restore($stateBackup);
        }
    }
}
