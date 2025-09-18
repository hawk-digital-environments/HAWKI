<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventBackHistory
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
     public function handle($request, Closure $next): mixed
     {
         // Skip middleware if this is a valid-signed route request
         if ($request->hasValidSignature()) {
             return $next($request);
         }
         $response = $next($request);

         return $response->header('Cache-Control','nocache, no-store, max-age=0, must-revalidate')
             ->header('Pragma','no-cache')
             ->header('Expires','Sun, 02 Jan 1990 00:00:00 GMT');

     }
}
