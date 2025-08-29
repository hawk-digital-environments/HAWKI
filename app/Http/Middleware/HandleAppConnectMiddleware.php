<?php

namespace App\Http\Middleware;

use App\Services\ExtApp\AppUserRequestSessionStorage;
use Closure;
use Illuminate\Http\Request;

class HandleAppConnectMiddleware
{
    public function __construct(
        protected AppUserRequestSessionStorage $sessionStorage
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->sessionStorage->get() !== null) {
            return redirect()->route('apps.confirm');
        }

        return $next($request);
    }
}
