<?php

namespace App\Providers;

use App\Services\Routing\CacheBusting\AssetCacheBustingUrlGenerator;
use App\Services\Routing\CacheBusting\CacheBusterGenerator;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('url', function (UrlGenerator $urlGenerator, Application $app) {
            $i = AssetCacheBustingUrlGenerator::createDecoratedOf($urlGenerator);
            $i->setCacheBusterGenerator($app->get(CacheBusterGenerator::class));
            return $i;
        });
    }

    public function boot(): void
    {
    }
}
