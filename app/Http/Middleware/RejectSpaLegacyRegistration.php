<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\SpaAuthHandoff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectSpaLegacyRegistration
{
    public function __construct(private readonly SpaAuthHandoff $handoff)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getUserContext()->isRegisteringUser()
            && ((bool) config('app.spa_auth', false) || $this->handoff->isSpaRegistration($request))) {
            abort(403, 'This registration must be completed through the current registration flow.');
        }

        return $next($request);
    }
}
