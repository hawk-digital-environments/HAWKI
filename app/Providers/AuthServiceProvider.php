<?php

namespace App\Providers;

use App\Services\Auth\ChainedAuthService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\LdapService;
use App\Services\Auth\OidcService;
use App\Services\Auth\ShibbolethService;
use App\Services\Auth\TestAuthService;
use Illuminate\Foundation\Application;
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

            // If the test authentication is enabled AND the selected auth service supports credentials,
            // wrap the selected auth service with the test auth service in a chained auth service.
            // This will first try to authenticate using the test auth service, and if it fails,
            // it will fall back to the selected auth service.
            if ($usesTestAuth && $instance instanceof AuthServiceWithCredentialsInterface) {
                $testAuth = $app->make(TestAuthService::class);
                $instance = new ChainedAuthService($testAuth, $instance);
            }

            return $instance;
        });
    }

    public function boot(): void
    {
    }
}
