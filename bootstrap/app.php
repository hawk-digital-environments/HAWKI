<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            Route::middleware(['web', 'platform'])
                ->prefix('admin')
                ->group(base_path('routes/platform.php'));
        },
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');

        // Register Orchid Platform middleware
        $middleware->alias([
            'platform' => \Orchid\Platform\Http\Middleware\Access::class,
            'chatAccess' => \App\Http\Middleware\ChatAccess::class,
            'groupChatAccess' => \App\Http\Middleware\GroupChatAccess::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->expectsJson() || $request->is('api/*');
        });
    })
    ->create();
