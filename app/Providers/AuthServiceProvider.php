<?php

namespace App\Providers;

use App\Services\Auth\ChainedAuthService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\LdapService;
use App\Services\Auth\LocalAuthService;
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
        'LOCAL_ONLY' => LocalAuthService::class
    ];

    public function register(): void
    {
        $this->app->singleton(AuthServiceInterface::class, function (Application $app) {
            $authenticationMethod = config('auth.authentication_method');
            if($authenticationMethod === 'CUSTOM'){
                $authenticationClass = config('auth.authentication_method_custom_class');
                if(empty($authenticationClass)){
                    throw new \RuntimeException('Custom authentication method selected, but no class configured.');
                }
                if(!class_exists($authenticationClass)){
                    throw new \RuntimeException(sprintf(
                        'Custom authentication method class %s does not exist.',
                        $authenticationClass
                    ));
                }
                $configuredAuthService = $authenticationClass;
            } elseif(is_string($authenticationMethod) && isset(self::LEGACY_AUTH_SERVICE_MAP[$authenticationMethod])){
                $configuredAuthService = self::LEGACY_AUTH_SERVICE_MAP[$authenticationMethod];
            } else {
                throw new \RuntimeException(sprintf(
                    'Invalid authentication method configured: %s',
                    is_string($authenticationMethod) ? $authenticationMethod : gettype($authenticationMethod)
                ));
            }

            // Special handling for
            $instance = $app->make($configuredAuthService);
            if (!$instance instanceof AuthServiceInterface) {
                throw new \RuntimeException(sprintf(
                    'Configured auth service %s does not implement %s',
                    $configuredAuthService,
                    AuthServiceInterface::class
                ));
            }

            // If the current auth service, supports login via a local form
            // We can chain it with additional authentication services based on the configuration
            if ($instance instanceof AuthServiceWithCredentialsInterface) {
                /**
                 * @var AuthServiceInterface[] $services
                 */
                $services = [$instance];

                // If test user authentication is enabled, add it as authenticator
                // This is deprecated, and we therefore add it AFTER local auth
                if (config('test_users.active', false)) {
                    array_unshift($services, $app->make(TestAuthService::class));
                }

                // If local auth is enabled, add it as authenticator
                if (!$instance instanceof LocalAuthService && config('auth.local_authentication')) {
                    array_unshift($services, $app->make(LocalAuthService::class));
                }

                if(count($services) > 1){
                    $instance = new ChainedAuthService(...$services);
                }
            }

            return $instance;
        });
    }

    public function boot(): void
    {
    }
}
