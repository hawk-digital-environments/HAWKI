<?php

namespace App\Http\Middleware;

use App\Services\ExtApp\AppUserRequestSessionStorage;
use App\Services\ExtApp\Value\AppUserRequestSessionValue;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

readonly class AppUserRequestRequiredMiddleware
{
    public function __construct(
        private AppUserRequestSessionStorage $sessionStorage,
        private Container                    $container
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $userRequest = $this->sessionStorage->get();
        if (!$userRequest) {
            return redirect()->route('login');
        }

        $this->container->instance(AppUserRequestSessionValue::class, $userRequest);

        return $next($request);
    }
}
