<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;



class MandatorySignatureCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $forceAnnouncements = Session::get('force_announcements', []);

        if (!empty($forceAnnouncements)) {
            return redirect('/chat');
        }
        return $next($request);
    }
}
