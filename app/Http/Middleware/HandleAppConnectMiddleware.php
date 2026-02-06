<?php

namespace App\Http\Middleware;

use App\Services\ExtApp\AppUserRequestSessionStorage;
use Closure;
use Illuminate\Http\Request;

readonly class HandleAppConnectMiddleware
{
    public function __construct(
        private AppUserRequestSessionStorage $sessionStorage
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->sessionStorage->get() !== null) {
            return redirect()->route('web.apps.confirm');
        }

        return $next($request);
    }
}
