<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Value;


use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;

final class ScopeOverrides
{
    use MakesDisableNotAllowedCallbacksTrait;

    private \Closure|null $contextConfigurator = null;
    private array $disabledScopes = [];
    private bool $allScopesDisabled = false;
    private \Closure|null $allScopesDisabledNotAllowedCallback = null;

    public function withContextConfigurator(\Closure $callback): self
    {
        $this->contextConfigurator = $callback;
        return $this;
    }

    public function withDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): self
    {
        $scopeKeys = is_array($scopeKey) ? $scopeKey : [$scopeKey];
        foreach ($scopeKeys as $key) {
            $this->disabledScopes[$key] = $notAllowedCallback;
        }
        return $this;
    }

    public function withForcefullyDisabled(string|array $scopeKey): self
    {
        return $this->withDisabled($scopeKey, $this->makeDisableNotAllowedForceDisable());
    }

    public function withAllDisabled(\Closure|null $notAllowedCallback = null): self
    {
        $this->allScopesDisabled = true;
        $this->allScopesDisabledNotAllowedCallback = $notAllowedCallback;
        return $this;
    }

    public function withAllForcefullyDisabled(): self
    {
        return $this->withAllDisabled($this->makeDisableNotAllowedForceDisable());
    }

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

    public static function make(
        true|array|string  $disableScopes = true,
        \Closure|true|null $onNotAllowed = null
    ): self
    {
        $overrides = new static();

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

    public static function makeWithDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): self
    {
        return (new static())->withDisabled($scopeKey, $notAllowedCallback);
    }

    public static function makeWithForcefullyDisabled(string|array $scopeKey): self
    {
        return (new static())->withForcefullyDisabled($scopeKey);
    }

    public static function makeWithAllDisabled(): self
    {
        return (new static())->withAllDisabled();
    }

    public static function makeWithAllForcefullyDisabled(): self
    {
        return (new static())->withAllForcefullyDisabled();
    }
}
