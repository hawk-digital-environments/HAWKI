<?php
declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\Admin\CacheDeploymentConfig;
use App\Services\Admin\SystemSettings;
use App\Services\Config\EnvironmentConfigProxy;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemSettings::class);
        $this->app->singleton(EnvironmentConfigProxy::class);
        // Both memoize per request/job; workers must not carry grants across requests.
        $this->app->scoped(\App\Services\Admin\PermissionService::class);
        $this->app->scoped(\App\Services\Admin\RoleGuard::class);
        $this->app->extend(\Illuminate\Foundation\Console\ConfigCacheCommand::class, fn() => new CacheDeploymentConfig($this->app['files']));
        $this->app->booting(function () {
            // Also covers optimize and programmatic Artisan::call('config:cache').
            if (CacheDeploymentConfig::isBuilding()) return;
            // A fresh installation must be able to boot before its first migration.
            try {
                if (Schema::hasTable('admin_settings')) $this->app->make(SystemSettings::class)->apply();
            } catch (\Illuminate\Database\QueryException $exception) {
                report($exception);
            }
        });
    }

    public function boot(): void
    {
        // Workers outlive HTTP requests and must also observe edits and resets.
        \Illuminate\Support\Facades\Queue::before(function () {
            $this->app->make(SystemSettings::class)->apply();
        });

        // The scoped binding is reset per queued job, but a container that handles more than one
        // request (Octane, and the test harness) must not carry memoized grants across them.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Foundation\Http\Events\RequestHandled::class,
            function (): void {
                $this->app->make(\App\Services\Admin\PermissionService::class)->forget();
            }
        );
    }
}
