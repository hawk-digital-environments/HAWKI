<?php

namespace App\Http\Middleware\ExtApp;

use Closure;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Error;

/**
 * Middleware to require an app token for accessing a resource.
 * If the request is not made with an app token, it returns a 403 Forbidden response.
 */
readonly class AppTokenRequiredMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->getUserContext()->isExternalApp()) {
            return Error::fromArray([
                'status' => 403,
                'title' => 'Forbidden',
                'detail' => 'It is required to use an app token to access this resource.'
            ])->toResponse($request);
        }

        return $next($request);
    }
}
