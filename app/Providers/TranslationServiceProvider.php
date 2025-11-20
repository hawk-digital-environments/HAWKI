<?php

namespace App\Providers;

use App\Services\Translation\LaravelTranslationLoaderAdapter;
use App\Services\Translation\Loader\TranslationFileLoader;
use App\Services\Translation\Loader\TranslationLoaderInterface;
use App\Services\Translation\LocaleService;
use App\Services\Translation\View\CurrentLocaleComponent;
use App\Services\Translation\View\CurrentLocaleJsonComponent;
use Blade;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TranslationLoaderInterface::class, function (Application $app) {
            return new TranslationFileLoader(
                $this->app->resourcePath('language/')
            );
        });
        
        // We need to register the translation service provider, because it is marked as "deferred" (meaning it is not loaded at this point).
        // Calling it like this will ensure that the translation service provider is loaded before we can override it.
        $this->app->register(\Illuminate\Translation\TranslationServiceProvider::class);
        $this->app->extend('translation.loader', function (FileLoader $service, Application $app) {
            return new LaravelTranslationLoaderAdapter(
                $app->make(TranslationLoaderInterface::class),
                $service,
                $app->make(LocaleService::class)
            );
        });
        
        // To ensure the translator is instantiated with our custom loader, we tell the container to forget the current instance of the translator.
        // Next time the translator is requested, it will use our custom loader. 👽️
        $this->app->forgetInstance('translator');
    }
    
    public function boot(): void
    {
        Blade::component('current-locale', CurrentLocaleComponent::class);
        Blade::component('current-locale-json', CurrentLocaleJsonComponent::class);
    }
}
