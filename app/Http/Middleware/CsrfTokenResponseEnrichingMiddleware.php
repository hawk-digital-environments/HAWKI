<?php
declare(strict_types=1);


namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that enriches HTTP responses with a CSRF token header.
 * This is useful for frontend applications that need to include the CSRF token
 * in their requests for security purposes. It allows the frontend to retrieve
 * the CSRF token from the response headers and use it in subsequent requests.
 */
class CsrfTokenResponseEnrichingMiddleware
{
    private const HEADER_NAME = 'X-HAWKI-CSRF-TOKEN';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {
            if ($response->headers->has(self::HEADER_NAME)) {
                return $response;
            }

            $token = $request->session()->token();
            $response->headers->set(self::HEADER_NAME, $token);
        }

        return $response;
    }
}
