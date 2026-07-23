<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


/**
 * Provides factory methods for the "not-allowed" callbacks used in the contextual scope system.
 *
 * A "not-allowed" callback is invoked by {@see \App\Services\System\Database\Eloquent\ContextualScopes\ContextualScopeWrapper}
 * when a scope's disabling guard returns false — meaning the caller is not permitted to bypass
 * that scope. The callback receives the scope key and the active {@see ModelScopeContext}.
 *
 * Return semantics:
 *  - `false` → the scope is NOT disabled; it will be applied normally.
 *  - `true`  → the scope IS force-disabled despite the guard's refusal.
 *  - (throw) → abort the request (default via {@see makeDisableNotAllowedThrowException()}).
 */
trait MakesDisableNotAllowedCallbacksTrait
{
    /**
     * Returns a callback that silently ignores the "not-allowed" signal and keeps the scope active.
     * Returning false tells the wrapper to apply the scope as if it were never asked to be disabled.
     */
    public function makeDisableNotAllowedIgnore(): \Closure
    {
        return static fn() => false; // False -> continue -> Apply anyway
    }

    /**
     * Returns a callback that aborts the request with a 403 error when a scope refuses to be disabled.
     * This is the default "not-allowed" behaviour registered on {@see \App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext}.
     */
    public function makeDisableNotAllowedThrowException(): \Closure
    {
        return static function (string $scopeKey, ModelScopeContext $context) {
            $modelClass = class_basename($context->modelClass);
            abort(403, "You can not disable scope: '{$scopeKey}' on '{$modelClass}' with this user or context.");
        };
    }

    /**
     * Returns a callback that force-disables the scope regardless of what the guard says.
     * Returning true tells the wrapper to skip the scope even though the guard refused.
     * Use with care — this bypasses security enforcement.
     */
    public function makeDisableNotAllowedForceDisable(): \Closure
    {
        return static fn() => true; // True -> return before apply -> Do not apply the scope
    }
}
