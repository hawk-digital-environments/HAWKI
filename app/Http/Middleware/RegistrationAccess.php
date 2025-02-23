<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RegistrationAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is fully authenticated, allow access
        if (Auth::check()) {
            return $next($request);
        }

        // Check if the user is authenticated via LDAP but hasn't completed full authentication
        if (Session::get('registration_access') && Session::get('registration_access') === true) {
            return $next($request);  // Allow access if LDAP login is successful
        }

        // Otherwise, redirect to login page or deny access
        return redirect('/login')->withErrors('You must authenticate first.');
    
    }
}
