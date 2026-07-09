<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


/**
 * Holds the contextual scope state for a single Eloquent model class.
 *
 * Obtained via {@see ScopeContext::getModelContext()} — never instantiate directly.
 * Manages which scopes are currently disabled for this model and provides the sandboxing
 * mechanism used by repositories to make per-query scope overrides without side effects.
 *
 * All mutations are scoped to a sandbox: {@see runSandboxed()} clones the current global
 * state, runs the closure, and restores the previous state on return. This ensures one
 * repository's query overrides do not leak into other queries on the same request.
 */
class ModelScopeContext
{
    use MakesDisableNotAllowedCallbacksTrait;

    public function __construct(
        public readonly string             $modelClass,
        private readonly \Closure          $sandBoxRunner,
        private readonly ScopeContextState $globalState,
    )
    {
    }

    /**
     * Overrides the default "not-allowed" callback for this model specifically.
     * When null, the global default from {@see ScopeContext} is used.
     */
    public function setDefaultOnDisabledNotAllowed(\Closure|null $onNotAllowed): self
    {
        $this->getLocalState()->defaultOnDisableNotAllowed = $onNotAllowed;
        return $this;
    }

    /**
     * Marks all contextual scopes on this model as locally disabled for the current sandbox.
     * The optional callback is invoked when a scope refuses to be bypassed.
     */
    public function setAllScopesLocallyDisabled(
        bool          $disabled = true,
        \Closure|null $onNotAllowed = null
    ): self
    {
        $localState = $this->getLocalState();
        $localState->allScopesLocallyDisabled = $disabled;
        $localState->allScopesLocallyDisabledNotAllowed = $disabled ? $onNotAllowed : null;
        return $this;
    }

    /**
     * Returns true when the given scope key is currently disabled — either because all
     * scopes are disabled globally/locally, or because this specific key was disabled via {@see disableScope()}.
     */
    public function isScopeDisabled(string $scopeKey): bool
    {
        if ($this->globalState->allScopesGloballyDisabled) {
            return true;
        }

        $localState = $this->getLocalState();
        if ($localState->allScopesLocallyDisabled) {
            return true;
        }

        return array_key_exists($scopeKey, $localState->disabledScopes);
    }

    /**
     * Marks a single scope key as disabled for the current sandbox.
     * The optional callback is invoked when the scope's guard refuses bypassing.
     * When null, the model-level or global default "not-allowed" callback applies.
     */
    public function disableScope(
        string        $scopeKey,
        \Closure|null $onNotAllowed = null
    ): self
    {
        $this->getLocalState()->disabledScopes[$scopeKey] = $onNotAllowed;
        return $this;
    }

    /**
     * Removes a previously disabled scope key, allowing the scope to be applied again.
     */
    public function resetScope(string $scopeKey): self
    {
        unset($this->getLocalState()->disabledScopes[$scopeKey]);
        return $this;
    }

    /**
     * Returns the effective "not-allowed" callback for the given scope key.
     *
     * Resolution order:
     *  1. Globally-disabled callback (if all scopes are globally disabled with a callback).
     *  2. Locally-disabled callback (if all scopes are locally disabled with a callback).
     *  3. Scope-specific callback registered via {@see disableScope()}.
     *  4. Global default from {@see ScopeContext}.
     */
    public function getOnDisableNotAllowed(string $scopeKey): \Closure
    {
        if ($this->globalState->allScopesGloballyDisabled && $this->globalState->onAllScopesGloballyDisabledNotAllowed) {
            return $this->globalState->onAllScopesGloballyDisabledNotAllowed;
        }

        $localState = $this->getLocalState();
        if ($localState->allScopesLocallyDisabled && $localState->allScopesLocallyDisabledNotAllowed) {
            return $localState->allScopesLocallyDisabledNotAllowed;
        }

        return $localState->disabledScopes[$scopeKey] ?? $this->globalState->defaultOnDisableNotAllowed;
    }

    /**
     * Runs a closure in a sandboxed scope context.
     *
     * Delegates to the global {@see ScopeContext::runSandboxed()} so both global and
     * model-local state are cloned and restored together. The closure receives this
     * {@see ModelScopeContext} and the parent {@see ScopeContext} as arguments.
     */
    public function runSandboxed(
        \Closure $callback
    ): mixed
    {
        return ($this->sandBoxRunner)(function (ScopeContext $globalContext) use ($callback) {
            return $callback($this, $globalContext);
        });
    }

    /**
     * Runs a closure with all scopes on this model locally disabled.
     * State is restored when the closure exits, as with {@see runSandboxed()}.
     */
    public function runSandboxedWithScopesLocallyDisabled(
        \Closure      $callback,
        \Closure|null $onNotAllowed = null
    ): mixed
    {
        return $this->runSandboxed(function () use ($callback, $onNotAllowed) {
            $this->setAllScopesLocallyDisabled(true, $onNotAllowed);
            return $callback($this);
        });
    }

    /**
     * Returns the full Eloquent global scope key used for this contextual scope on this model.
     * Format: {@code context-aware-scope:ModelScopeContext:{scopeKey}}.
     */
    public function getFullScopeKey(string $scopeKey): string
    {
        return 'context-aware-scope:' . class_basename(static::class) . ':' . $scopeKey;
    }

    private function getLocalState(): ModelScopeContextState
    {
        return $this->globalState->getOrMakeModelScopeContextState($this->modelClass);
    }
}
