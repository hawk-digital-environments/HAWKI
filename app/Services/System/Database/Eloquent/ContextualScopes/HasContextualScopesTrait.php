<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes;


use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;

/**
 * Enables per-request contextual scope management on an Eloquent model.
 *
 * Contextual scopes are Eloquent global scopes that can be selectively disabled for individual
 * queries without touching global state. Unlike plain global scopes — which are either always
 * applied or manually removed via `withoutGlobalScope()` — contextual scopes:
 *
 *  - Are registered by key in {@see registerScopes()}, which every model must implement.
 *  - Are controlled through a {@see ScopeContext} singleton rather than being removed from
 *    the Eloquent builder directly.
 *  - Support a per-scope "disabling guard" that can refuse bypassing (e.g. restrict to admins).
 *  - Support sandboxed execution: mutations to scope state are automatically restored after
 *    the sandbox closure returns, preventing per-query overrides from leaking across requests.
 *
 * Usage (in an Eloquent model):
 * ```php
 * class AiModel extends Model
 * {
 *     use HasContextualScopesTrait;
 *
 *     protected static function registerScopes(ScopeRegistrar $registrar): void
 *     {
 *         $registrar->addScope(
 *             scopeKey: 'active_filter',
 *             scope: ActiveModelScope::class,
 *             // Guard: only the current user's permission check may bypass this scope.
 *             disablingGuard: static fn(): bool => auth()->user()?->isAdmin() ?? false,
 *         );
 *     }
 * }
 *
 * // Disable the scope just for one query in a repository:
 * AiModel::scopeContext()->runSandboxed(function (ModelScopeContext $ctx): void {
 *     $ctx->disableScope('active_filter');
 *     $allModels = AiModel::all(); // bypasses the active filter
 * });
 *
 * // Or use AbstractRepositoryWithContextualScopes for the standard repository API.
 * ```
 *
 * @see ScopeRegistrar     Where scopes are registered during model boot.
 * @see ScopeContext       The singleton holding global scope state.
 * @see ModelScopeContext  Per-model scope state (obtained via {@see scopeContext()}).
 * @see \App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes
 *      Repository base class that integrates with this trait.
 */
trait HasContextualScopesTrait
{
    protected static ServiceLocator $hcst_serviceLocator;
    protected static ScopeContext $hcst_scopeContext;
    protected static bool $hcst_dependenciesInjected = false;

    /**
     * Registers all contextual scopes for this model on the given {@see ScopeRegistrar}.
     * Called once during {@see bootHasContextualScopesTrait()}.
     */
    abstract protected static function registerScopes(ScopeRegistrar $registrar): void;

    /**
     * Injects the service locator and scope context directly — intended for testing
     * to avoid bootstrapping the full container.
     */
    public static function setDependenciesOfHasContextualScopesTrait(
        ServiceLocator $serviceLocator,
        ScopeContext   $scopeContext
    ): void
    {
        static::$hcst_serviceLocator = $serviceLocator;
        static::$hcst_scopeContext = $scopeContext;
        static::$hcst_dependenciesInjected = true;
    }

    /**
     * Laravel boot hook — called automatically by Eloquent on the first model instantiation.
     * Resolves dependencies (or uses injected ones), calls {@see registerScopes()}, and
     * registers one {@see ContextualScopeWrapper} per scope as an Eloquent global scope.
     *
     * Runs again whenever Eloquent has dropped the model's global scopes (the test runner calls
     * Model::clearBootedModels() between tests and rebuilds the application). A static "booted"
     * flag would leave the model without any contextual scope from the second boot on, and the
     * dependencies would still point at the previous container, so they are re-resolved as well
     * unless a test injected them explicitly.
     */
    public static function bootHasContextualScopesTrait(): void
    {
        if (static::hasRegisteredContextualScopes()) {
            return;
        }

        // If the dependencies were not injected manually, we resolve them from the container.
        if (!static::$hcst_dependenciesInjected) {
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
     * Returns all registered {@see ContextualScopeWrapper} instances for this model,
     * keyed by their short scope key.
     *
     * @return array<string, ContextualScopeWrapper>
     */
    public static function getContextualScopes(): array
    {
        static::bootHasContextualScopesTrait();
        return static::collectContextualScopes();
    }

    /**
     * True while the {@see ContextualScopeWrapper} instances of this model are registered as
     * Eloquent global scopes, false before the first boot and after Eloquent cleared them.
     */
    protected static function hasRegisteredContextualScopes(): bool
    {
        return static::collectContextualScopes() !== [];
    }

    /**
     * @return array<string, ContextualScopeWrapper>
     */
    private static function collectContextualScopes(): array
    {
        return collect(static::getAllGlobalScopes()[static::class] ?? [])
            ->filter(fn($scope) => $scope instanceof ContextualScopeWrapper)
            ->keyBy(fn($scope) => $scope->getScopeKey())
            ->toArray();
    }

    /**
     * Returns the {@see ModelScopeContext} for this model, which is the primary entry point
     * for disabling scopes per-query or running sandboxed closures.
     */
    public static function scopeContext(): ModelScopeContext
    {
        static::bootHasContextualScopesTrait();
        return static::$hcst_scopeContext->getModelContext(static::class);
    }
}
