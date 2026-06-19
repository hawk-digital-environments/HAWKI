<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use Illuminate\Container\Attributes\Singleton;

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

    public function setDefaultIsDisablingAllowedGuard(\Closure $guard): self
    {
        $this->globalState->defaultIsDisablingAllowedGuard = $guard;
        return $this;
    }

    public function getDefaultIsDisablingAllowedGuard(): \Closure
    {
        return $this->globalState->defaultIsDisablingAllowedGuard;
    }

    public function setDefaultOnDisabledNotAllowed(\Closure $onNotAllowed): self
    {
        $this->globalState->defaultOnDisableNotAllowed = $onNotAllowed;
        return $this;
    }

    public function setAllScopesGloballyDisabled(bool $disabled = true, \Closure|null $onNotAllowed = null): self
    {
        $this->globalState->allScopesGloballyDisabled = $disabled;
        $this->globalState->onAllScopesGloballyDisabledNotAllowed = $disabled ? $onNotAllowed : null;
        return $this;
    }

    public function getModelContext(string $modelClass): ModelScopeContext
    {
        return $this->modelContexts[$modelClass] ??= new ModelScopeContext(
            modelClass: $modelClass,
            sandBoxRunner: $this->runSandboxed(...),
            globalState: $this->globalState
        );
    }

    // Backup the state of the context, run the callback, and then restore the state of the context, return the result of callback
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
