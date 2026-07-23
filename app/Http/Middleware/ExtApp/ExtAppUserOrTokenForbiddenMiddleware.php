<?php

namespace App\Http\Middleware\ExtApp;

use Closure;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Error;

/**
 * Middleware to forbid access to a resource when the request is made from an external app.
 * If the request is made from an external app, it returns a 403 Forbidden response.
 */
readonly class ExtAppUserOrTokenForbiddenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getUserContext()->isExternalApp() ||
            $request->getUsageContext()->isExternalApp()) {
            return Error::fromArray([
                'status' => 403,
                'title' => 'Forbidden',
                'detail' => 'It is not allowed to access this resource from an external app.',
            ])->toResponse($request);
        }
        return $next($request);
    }
}
