<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalCommunicationCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if (! config('sanctum.allow_external_communication', false)) {
            return response()->json(['response' => 'External communication is not allowed. Please contact the administration for more information.'], 403);
        }

        return $next($request);
    }
}
