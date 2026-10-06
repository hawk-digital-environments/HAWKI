<?php

declare(strict_types=1);

use App\Http\Middleware\LocaleSettingMiddleware;
use App\Http\Middleware\SystemContextBootingMiddleware;
use App\Services\System\Plugins\InstalledPlugins;
use App\Services\System\Plugins\PluginRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Exceptions\JsonApiException;
use LaravelJsonApi\Exceptions\ExceptionParser;

$pluginRegistry = InstalledPlugins::getRegistry();

return Application::configure(basePath: dirname(__DIR__))
    ->withSingletons([PluginRegistry::class => static fn (): PluginRegistry => InstalledPlugins::getRegistry()])
    ->withProviders($pluginRegistry->getServiceProviders())
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withEvents([
        __DIR__ . '/../app/Services/*/Listeners',
        ...$pluginRegistry->getEventListenerPaths(),
    ])
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->statefulApi();
        $middleware->web(append: [
            LocaleSettingMiddleware::class,
            SystemContextBootingMiddleware::class,
        ]);
        $middleware->api(append: [
            LocaleSettingMiddleware::class,
            SystemContextBootingMiddleware::class,
        ]);
    })
    ->withBroadcasting(
        channels: __DIR__ . '/../routes/channels.php',
        attributes: [
            'prefix' => 'api',
            'middleware' => ['auth:sanctum', 'external_access:enabled,apps', 'app_access:declined'],
        ],
    )
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->dontReport(JsonApiException::class);
        $exceptions->render(ExceptionParser::renderer());
        $exceptions->shouldRenderJsonWhen(static function (Request $request) {
            return $request->expectsJson() || $request->is('api/*');
        });
        // Ensures that if we are in our api endpoint, we always return exception responses in the json api format
        $exceptions->renderable(static function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ExceptionParser::make()->accept(static fn () => true)->render($e, $request);
            }

            return null;
        });
    })
    ->create();
