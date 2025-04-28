<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class SessionExpiryChecker
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(Auth::user()->isRemoved === 1){
            return redirect('/logout')->withErrors('Your accound has been suspended.');
        }

        if(Session::get('lastActivity')){
            if ((time() - Session::get('lastActivity')) > (config('session.lifetime') * 60))
            {
                return redirect('/logout')->withErrors('You must authenticate first.');
            }
        }
        Session::put('lastActivity',time());

        return $next($request);
    }
}
