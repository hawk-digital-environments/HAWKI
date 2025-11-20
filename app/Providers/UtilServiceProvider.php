<?php

namespace App\Providers;

use App\Utils\AbstractCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class UtilServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app->afterResolving(AbstractCache::class, function (AbstractCache $cache, Application $app) {
            $cache->setRepository($app->make(Repository::class));
        });
    }
}
