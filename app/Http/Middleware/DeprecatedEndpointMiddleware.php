<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * A middleware to mark specific endpoints as deprecated.
 * When a request is made to a deprecated endpoint, this middleware will:
 * 1. Log a warning message indicating that the deprecated endpoint was accessed.
 * 2. Add appropriate HTTP headers to the response to inform clients about the deprecation.
 *
 * The middleware has an alias of "deprecated" and can be used:
 * ```php
 * Route::get('/old-endpoint', [OldController::class, 'oldMethod'])->middleware('deprecated:/new-endpoint');
 * ```
 * In this example, accessing `/old-endpoint` will log a warning and add deprecation headers to the response,
 * including a `Link` header pointing to `/new-endpoint` as the alternative. The parameter is optional.
 */
readonly class DeprecatedEndpointMiddleware
{
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    public function handle(Request $request, Closure $next, $alternativeEndpoint = null): Response
    {
        $this->logger->warning('Deprecated endpoint accessed', ['url' => $request->fullUrl()]);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', 'Unknown');
            $response->headers->set('Warning', '299 - "This endpoint is deprecated and will be removed in a future version."');

            if ($alternativeEndpoint) {
                $response->headers->set('Link', '<' . $alternativeEndpoint . '>; rel="successor-version"');
            }
        }

        return $response;
    }
}
