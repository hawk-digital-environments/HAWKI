<?php

namespace App\Http\Middleware\ExtApp;

use Closure;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Error;

/**
 * Middleware to forbid access to a resource when an app token is used.
 * If the request is made with an app token, it returns a 403 Forbidden response.
 */
readonly class AppTokenForbiddenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getUserContext()->isExternalApp()) {
            return Error::fromArray([
                'status' => 403,
                'title' => 'Forbidden',
                'detail' => 'It is not allowed to use an app token to access this resource.',
            ])->toResponse($request);
        }

        return $next($request);
    }
}
