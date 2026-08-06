<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Http\Middleware\SystemContextBootingMiddleware;
use App\Models\User;
use App\Services\System\Container\SystemEnvironment;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;

trait UserAwareScopeTrait
{
    use ServiceLocatingScopeTrait;

    private bool $uast_isRunningInConsole;
    private \Closure $uast_currentUserResolver;
    private \Closure $uast_onNoUser;

    public function initializeUserAwareScopeTrait(SystemEnvironment $environment): void
    {
        $this->uast_isRunningInConsole = $environment->runningInConsole();
        $this->uast_currentUserResolver = static fn(Factory $auth) => $auth->guard()->user();
        $this->uast_onNoUser = static function () {
            tracee();
            abort(403, sprintf("No authenticated user found for applying scope: '%s'.", class_basename(static::class)));
        };
    }

    public function withOnNoUser(\Closure $callback): static
    {
        $this->uast_onNoUser = $callback;
        return $this;
    }

    protected function getCurrentUser(): User|null
    {
        $user = $this->serviceLocator->call('userAwareScope.currentUser', $this->uast_currentUserResolver);
        if ($user instanceof User) {
            return $user;
        }
        return null;
    }

    protected function runIfUserPresent(
        \Closure           $callback,
        \Closure|true|null $callbackNoUserInCli = null
    ): mixed
    {
        $user = $this->getCurrentUser();
        if ($user) {
            return $callback($user);
        }

        if ($callbackNoUserInCli && $this->uast_isRunningInConsole) {
            if ($callbackNoUserInCli === true) {
                return null;
            }
            return $callback();
        }

        // This is a bypass for the early "routing" stage.
        // While we are traversing the middlewares for web-requests,
        // The "Authenticator" must have access to all users without scoping, because the user is not yet authenticated and therefore not yet available.
        // Our SystemContextBootingMiddleware will set a metadata flag on the route, which means from that point forward we can safely apply the user-aware scoping,
        // because the user is now available and authenticated.
        // Before that all user based scoping is disabled, because we cannot determine the user yet.
        if (!$this->uast_isRunningInConsole) {
            $request = $this->serviceLocator->get(Request::class);
            $route = $request ? $request->route() : null;
            if ($route) {
                $metadata = $route->getMetadata();
                if (!($metadata[SystemContextBootingMiddleware::SYSTEM_CONTEXT_BOOTED_METADATA_KEY] ?? false)) {
                    return null;
                }
            }
        }

        return $this->serviceLocator->call('userAwareScope.onNoUser', $this->uast_onNoUser);
    }
}
