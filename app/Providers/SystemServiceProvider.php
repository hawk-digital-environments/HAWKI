<?php

namespace App\Providers;

use App\Models\User;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\Users\UserCondition;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class SystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScopeContext::class, function (Application $app) {
            // This is currently a rather simple default guard, can be replaced in the future with
            // more complex logic, e.g. checking user permissions, etc.
            return new ScopeContext(
                defaultIsDisablingAllowedGuard: function (
                    #[CurrentUser]
                    ?User       $user,
                    Application $application
                ) {
                    if (!$user) {
                        return $application->runningInConsole();
                    }
                    return UserCondition::isAdmin($user);
                }
            );
        });

        // Automatically make all repositories singletons
        $this->app->afterResolving(AbstractRepository::class, function ($repository, Application $app) {
            $app->singleton(get_class($repository), fn() => $repository);
            return $repository;
        });
    }

    public function boot(): void
    {
    }
}
