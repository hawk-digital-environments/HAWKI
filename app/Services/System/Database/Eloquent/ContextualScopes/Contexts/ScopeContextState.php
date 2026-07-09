<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


/**
 * Plain mutable value object holding the global contextual scope state for one snapshot.
 *
 * Owned by {@see ScopeContext}. Cloned before running a sandboxed closure and restored
 * via {@see restore()} when the closure exits, ensuring that per-query scope mutations
 * do not leak between requests (or between different callers in a long-running process).
 *
 * @internal
 */
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
