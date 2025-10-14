<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenCreationCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sanctum.allow_user_token', false) === false) {
            return response()->json([
                'success' => false,
                'message' => 'Token creation by users is disabled. Please contact your administrator for API access.'
            ], 403);
        }
        
        return $next($request);
    }
}