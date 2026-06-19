<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Value;


use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;

class ScopeOverrides
{
    use MakesDisableNotAllowedCallbacksTrait;

    private \Closure|null $contextConfigurator = null;
    private array $disabledScopes = [];
    private bool $allScopesDisabled = false;
    private \Closure|null $allScopesDisabledNotAllowedCallback = null;

    public function withContextConfigurator(\Closure $callback): static
    {
        $this->contextConfigurator = $callback;
        return $this;
    }

    public function withDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): static
    {
        $scopeKeys = is_array($scopeKey) ? $scopeKey : [$scopeKey];
        foreach ($scopeKeys as $key) {
            $this->disabledScopes[$key] = $notAllowedCallback;
        }
        return $this;
    }

    public function withForcefullyDisabled(string|array $scopeKey): static
    {
        return $this->withDisabled($scopeKey, $this->makeDisableNotAllowedForceDisable());
    }

    public function withAllDisabled(\Closure|null $notAllowedCallback = null): static
    {
        $this->allScopesDisabled = true;
        $this->allScopesDisabledNotAllowedCallback = $notAllowedCallback;
        return $this;
    }

    public function withAllForcefullyDisabled(): static
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
    ): static
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

    public static function makeWithDisabled(string|array $scopeKey, \Closure|null $notAllowedCallback = null): static
    {
        return (new self())->withDisabled($scopeKey, $notAllowedCallback);
    }

    public static function makeWithForcefullyDisabled(string|array $scopeKey): static
    {
        return (new self())->withForcefullyDisabled($scopeKey);
    }

    public static function makeWithAllDisabled(): static
    {
        return (new self())->withAllDisabled();
    }

    public static function makeWithAllForcefullyDisabled(): static
    {
        return (new self())->withAllForcefullyDisabled();
    }
}
