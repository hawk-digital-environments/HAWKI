<?php

namespace App\Http\Middleware;

use App\Services\ExtApp\Config\ExtAppConfig;
use Closure;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Error;

/**
 * Middleware to check if external access is enabled in the application configuration.
 * If external access is disabled, it returns a 403 Forbidden response.
 */
readonly class ExternalAccessRequiredMiddleware
{
    public function __construct(
        private ExtAppConfig $extAppConfig
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->extAppConfig->externalAccess) {
            return Error::fromArray([
                'status' => '403',
                'title' => 'Forbidden',
                'detail' => 'External access is disabled.',
            ])->toResponse($request);
        }

        return $next($request);
    }
}
