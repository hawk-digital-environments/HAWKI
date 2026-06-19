<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


class ScopeContextState
{
    public function __construct(
        public \Closure      $defaultIsDisablingAllowedGuard,
        public \Closure      $defaultOnDisableNotAllowed,
        public bool          $allScopesGloballyDisabled = false,
        public \Closure|null $onAllScopesGloballyDisabledNotAllowed = null,
        private array        $modelStates = [],
    )
    {
    }

    public function getOrMakeModelScopeContextState(string $modelClass): ModelScopeContextState
    {
        if (!isset($this->modelStates[$modelClass])) {
            $this->modelStates[$modelClass] = new ModelScopeContextState();
        }
        return $this->modelStates[$modelClass];
    }

    public function clone(): self
    {
        return new self(
            $this->defaultIsDisablingAllowedGuard,
            $this->defaultOnDisableNotAllowed,
            $this->allScopesGloballyDisabled,
            $this->onAllScopesGloballyDisabledNotAllowed,
            array_map(
                static fn(ModelScopeContextState $state) => $state->clone(),
                $this->modelStates
            )
        );
    }

    public function restore(self $backup): void
    {
        $this->defaultOnDisableNotAllowed = $backup->defaultOnDisableNotAllowed;
        $this->allScopesGloballyDisabled = $backup->allScopesGloballyDisabled;
        $this->onAllScopesGloballyDisabledNotAllowed = $backup->onAllScopesGloballyDisabledNotAllowed;
        $this->modelStates = array_map(
            static fn(ModelScopeContextState $state) => $state->clone(),
            $backup->modelStates
        );
    }
}
