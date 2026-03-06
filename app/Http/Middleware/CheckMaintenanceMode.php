<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * Bypass maintenance mode for authenticated admin users.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if app is in maintenance mode
        if (app()->isDownForMaintenance()) {
            // Allow access for authenticated users with admin permissions
            if (Auth::check() && Auth::user()->hasAccess('platform.systems.settings')) {
                return $next($request);
            }
        }

        return $next($request);
    }
}
