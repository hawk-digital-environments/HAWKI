<?php
declare(strict_types=1);


namespace App\Http\Middleware\Api;


use App\Services\ExtApp\Config\ExtAppConfig;
use Closure;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Error;

/**
 * Middleware to block external apps from accessing the API if they are not allowed in the application configuration.
 * It checks if the request is coming from an external app and if external access and external apps are enabled in the configuration.
 * If any of these conditions are not met, it returns a 403 Forbidden response.
 */
readonly class BlockExtAppsIfNotAllowedMiddleware
{
    public function __construct(
        private ExtAppConfig $extAppConfig
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->getUsageContext()->isExternalApp()) {
            return $next($request);
        }

        if (!$this->extAppConfig->externalAccess) {
            return Error::fromArray([
                'status' => '403',
                'title' => 'Forbidden',
                'detail' => 'External access is disabled.',
            ])->toResponse($request);
        }

        if (!$this->extAppConfig->externalApps) {
            return Error::fromArray([
                'status' => '403',
                'title' => 'Forbidden',
                'detail' => 'External apps are not allowed to access the api.',
            ])->toResponse($request);
        }

        return $next($request);
    }
}
