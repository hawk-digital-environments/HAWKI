<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Value;


use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;

/**
 * A value object that bundles a set of contextual scope-disabling instructions for a single query.
 *
 * Pass a configured instance to {@see AbstractRepositoryWithContextualScopes::getQuery()} (or its
 * public wrappers) to temporarily bypass specific — or all — contextual scopes without touching
 * the global scope context.
 *
 * Each disabled scope can carry an optional "not-allowed" callback:
 * - `null`  → the scope's own default enforcement is used (may abort with 403).
 * - Closure returning `false` → scope enforcement is skipped; the scope still applies.
 * - Closure returning `true`  → enforcement is skipped and the scope is force-disabled.
 * - {@see makeDisableNotAllowedForceDisable()} → pre-built force-disable callback.
 *
 * Usage:
 * ```php
 * // Disable a single scope, using force-disable to bypass its "not-allowed" guard:
 * $overrides = ScopeOverrides::makeWithForcefullyDisabled(ScopeKeys::ACTIVE_FILTER);
 *
 * // Disable multiple scopes at once, forwarding to the scope's own "not-allowed" guard:
 * $overrides = ScopeOverrides::makeWithDisabled([ScopeKeys::USAGE_TYPE, ScopeKeys::ACTIVE_FILTER]);
 *
 * // Disable all scopes unconditionally (e.g. for admin queries):
 * $overrides = ScopeOverrides::makeWithAllForcefullyDisabled();
 * ```
 */
class ScopeOverrides
{
    use MakesDisableNotAllowedCallbacksTrait;

    private \Closure|null $contextConfigurator = null;
    private array $disabledScopes = [];
    private bool $allScopesDisabled = false;
    private \Closure|null $allScopesDisabledNotAllowedCallback = null;

    /**
     * Registers an arbitrary closure that receives the {@see ModelScopeContext} directly.
     * Use when the built-in disable/enable helpers are insufficient for a specific scope configuration.
     */
    public function withContextConfigurator(\Closure $callback): self
    {
        $this->contextConfigurator = $callback;
        return $this;
    }

    /**
     * Marks one or more scope keys as disabled for this query.
     * The optional callback is invoked when a scope refuses to be bypassed.
     * When null, the scope's own "not-allowed" enforcement applies.
     */
    public function withDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): self
    {
        $scopeKeys = is_array($scopeKey) ? $scopeKey : [$scopeKey];
        foreach ($scopeKeys as $key) {
            $this->disabledScopes[$key] = $notAllowedCallback;
        }
        return $this;
    }

    /**
     * Marks one or more scope keys as force-disabled, bypassing any "not-allowed" guard they define.
     */
    public function withForcefullyDisabled(string|array $scopeKey): self
    {
        return $this->withDisabled($scopeKey, $this->makeDisableNotAllowedForceDisable());
    }

    /**
     * Marks all contextual scopes on the model as disabled for this query.
     * The optional callback is invoked for any scope that refuses to be bypassed.
     */
    public function withAllDisabled(\Closure|null $notAllowedCallback = null): self
    {
        $this->allScopesDisabled = true;
        $this->allScopesDisabledNotAllowedCallback = $notAllowedCallback;
        return $this;
    }

    /**
     * Marks all contextual scopes as force-disabled, bypassing every "not-allowed" guard.
     */
    public function withAllForcefullyDisabled(): self
    {
        return $this->withAllDisabled($this->makeDisableNotAllowedForceDisable());
    }

    /**
     * Applies the registered overrides to the given context.
     * Called internally by the repository before the query builder is returned.
     */
    public function apply(ModelScopeContext $context): void
    {
        if ($this->contextConfigurator) {
            ($this->contextConfigurator)($context);
        }

        if ($this->allScopesDisabled) {
            $context->setAllScopesLocallyDisabled(true, $this->allScopesDisabledNotAllowedCallback);
        }

        foreach ($this->disabledScopes as $scopeKey => $onNotAllowed) {
            $context->disableScope($scopeKey, $onNotAllowed);
        }
    }

    /**
     * Creates a new instance that disables the given scope keys (or all, when `true`).
     *
     * @param true|array|string $disableScopes Scope keys to disable, or `true` to disable all.
     * @param \Closure|true|null $onNotAllowed "Not-allowed" callback. Pass `true` to force-disable.
     */
    public static function make(
        true|array|string  $disableScopes = true,
        \Closure|true|null $onNotAllowed = null
    ): self
    {
        $overrides = new self();

        $onNotAllowed = $onNotAllowed === true
            ? $overrides->makeDisableNotAllowedForceDisable()
            : $onNotAllowed;

        if ($disableScopes === true) {
            $overrides->withAllDisabled($onNotAllowed);
        } else {
            $overrides->withDisabled($disableScopes, $onNotAllowed);
        }

        return $overrides;
    }

    /**
     * Creates a new instance that disables the given scope key(s) with an optional "not-allowed" callback.
     */
    public static function makeWithDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): self
    {
        return (new self())->withDisabled($scopeKey, $notAllowedCallback);
    }

    /**
     * Creates a new instance that force-disables the given scope key(s),
     * ignoring any "not-allowed" guard they define.
     */
    public static function makeWithForcefullyDisabled(string|array $scopeKey): self
    {
        return (new self())->withForcefullyDisabled($scopeKey);
    }

    /**
     * Creates a new instance that disables all contextual scopes on the model.
     */
    public static function makeWithAllDisabled(): self
    {
        return (new self())->withAllDisabled();
    }

    /**
     * Creates a new instance that force-disables all contextual scopes on the model.
     */
    public static function makeWithAllForcefullyDisabled(): self
    {
        return (new self())->withAllForcefullyDisabled();
    }
}
