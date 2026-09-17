<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Auth\ChainedAuthService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\LdapService;
use App\Services\Auth\LocalAuthService;
use App\Services\Auth\OidcService;
use App\Services\Auth\ShibbolethService;
use App\Services\Auth\TestAuthService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * To allow custom authentication methods while maintaining backward compatibility
     * with legacy configuration values, we map legacy auth method names to their
     * corresponding service classes here.
     */
    private const LEGACY_AUTH_SERVICE_MAP = [
        'LDAP' => LdapService::class,
        'Shibboleth' => ShibbolethService::class,
        'OIDC' => OidcService::class,
    ];

    public function register(): void
    {
        $this->app->singleton(AuthServiceInterface::class, function (Application $app) {
            $usesTestAuth = config('test_users.active', false);

            // This can be either a legacy auth method name or a fully qualified class name.
            $configuredAuthService = config('auth.authMethod');
            $configuredAuthService = self::LEGACY_AUTH_SERVICE_MAP[$configuredAuthService] ?? $configuredAuthService;

            $instance = $app->make($configuredAuthService);
            if (!$instance instanceof AuthServiceInterface) {
                throw new \RuntimeException(sprintf(
                    'Configured auth service %s does not implement %s',
                    $configuredAuthService,
                    AuthServiceInterface::class
                ));
            }

            $services = [$app->make(LocalAuthService::class)];

            if ($usesTestAuth && $instance instanceof AuthServiceWithCredentialsInterface) {
                $services[] = $app->make(TestAuthService::class);
            }

            if (!$instance instanceof LocalAuthService) {
                $services[] = $instance;
            }

            return count($services) === 1 ? $services[0] : new ChainedAuthService(...$services);
        });
    }

    public function boot(): void
    {
        // Replace Spatie's granting callback so account checks always run first.
        // Unknown abilities and permission misses continue to resource policies.
        Gate::before(static function (User $user, string $ability): ?bool {
            $permissions = app(PermissionService::class);

            if (!$permissions->isEligible($user)) {
                return false;
            }

            // Abilities outside the catalogue never need the grant list.
            if (!Permission::tryFrom($ability)) {
                return null;
            }

            // Memoized, so the eligibility check above and this lookup share one resolution.
            return \in_array($ability, $permissions->permissionsOf($user), true) ? true : null;
        });
    }
}
