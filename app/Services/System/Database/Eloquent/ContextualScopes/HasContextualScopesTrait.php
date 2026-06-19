<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;

trait HasContextualScopesTrait
{
    protected static ServiceLocator $hcst_serviceLocator;
    protected static ScopeContext $hcst_scopeContext;
    protected static bool $hcst_booted = false;

    abstract protected static function registerScopes(ScopeRegistrar $registrar): void;

    public static function setDependenciesOfHasContextualScopesTrait(
        ServiceLocator $serviceLocator,
        ScopeContext   $scopeContext
    ): void
    {
        static::$hcst_serviceLocator = $serviceLocator;
        static::$hcst_scopeContext = $scopeContext;
    }

    public static function bootHasContextualScopesTrait(): void
    {
        if (static::$hcst_booted) {
            return;
        }
        static::$hcst_booted = true;

        // If the dependencies were not injected manually, we resolve them from the container.
        if (!isset(static::$hcst_serviceLocator, static::$hcst_scopeContext)) {
            static::$hcst_serviceLocator = app(ServiceLocator::class);
            static::$hcst_scopeContext = app(ScopeContext::class);
        }

        $registrar = new ScopeRegistrar(
            static::class,
            static::$hcst_scopeContext->getDefaultIsDisablingAllowedGuard()
        );

        static::registerScopes($registrar);

        $modelContext = static::$hcst_scopeContext->getModelContext(static::class);

        foreach ($registrar as $scopeKey) {
            static::addGlobalScope(
                $modelContext->getFullScopeKey($scopeKey),
                new ContextualScopeWrapper(
                    scopeKey: $scopeKey,
                    registrar: $registrar,
                    context: $modelContext,
                    serviceLocator: static::$hcst_serviceLocator
                )
            );
        }
    }

    /**
     * @return array<string, ContextualScopeWrapper>
     */
    public static function getContextualScopes(): array
    {
        static::bootHasContextualScopesTrait();
        return collect(static::getAllGlobalScopes()[static::class] ?? [])
            ->filter(fn($scope) => $scope instanceof ContextualScopeWrapper)
            ->keyBy(fn($scope) => $scope->getScopeKey())
            ->toArray();
    }

    public static function scopeContext(): ModelScopeContext
    {
        static::bootHasContextualScopesTrait();
        return static::$hcst_scopeContext->getModelContext(static::class);
    }
}
