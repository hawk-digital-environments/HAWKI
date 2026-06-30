<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Exceptions\JsonApiException;
use LaravelJsonApi\Exceptions\ExceptionParser;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        $middleware->convertEmptyStringsToNull([
            fn (Request $request) => $request->is('api/assistants*'),
            fn (Request $request) => $request->is('api/assistant-setting-values*'),
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(
            JsonApiException::class,
        );
        $exceptions->render(
            ExceptionParser::renderer(),
        );
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->expectsJson() || $request->is('api/*');
        });
    })
    ->create();
