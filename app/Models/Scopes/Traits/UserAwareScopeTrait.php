<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Application;

trait UserAwareScopeTrait
{
    use ServiceLocatingScopeTrait;

    private bool $uast_isRunningInConsole;
    private \Closure $uast_currentUserResolver;
    private \Closure $uast_onNoUser;

    public function initializeUserAwareScopeTrait(Application $application): void
    {
        $this->uast_isRunningInConsole = $application->runningInConsole();
        $this->uast_currentUserResolver = static fn(Guard $auth) => $auth->user();
        $this->uast_onNoUser = static function () {
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

        return $this->serviceLocator->call('userAwareScope.onNoUser', $this->uast_onNoUser);
    }
}
