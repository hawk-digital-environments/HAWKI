<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


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

    public function setDefaultOnDisabledNotAllowed(\Closure|null $onNotAllowed): self
    {
        $this->getLocalState()->defaultOnDisableNotAllowed = $onNotAllowed;
        return $this;
    }

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

    public function disableScope(
        string        $scopeKey,
        \Closure|null $onNotAllowed = null
    ): self
    {
        $this->getLocalState()->disabledScopes[$scopeKey] = $onNotAllowed;
        return $this;
    }

    public function resetScope(string $scopeKey): self
    {
        unset($this->getLocalState()->disabledScopes[$scopeKey]);
        return $this;
    }

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

    // Backup the state of the context, run the callback, and then restore the state of the context, return the result of callback
    public function runSandboxed(
        \Closure $callback
    ): mixed
    {
        return ($this->sandBoxRunner)(function (ScopeContext $globalContext) use ($callback) {
            return $callback($this, $globalContext);
        });
    }

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

    public function getFullScopeKey(string $scopeKey): string
    {
        return 'context-aware-scope:' . class_basename(static::class) . ':' . $scopeKey;
    }

    private function getLocalState(): ModelScopeContextState
    {
        return $this->globalState->getOrMakeModelScopeContextState($this->modelClass);
    }
}
