<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\SpaAuthHandoff;
use App\Http\Errors\CodedError;
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
            && $this->handoff->requiresSpaRegistration($request)) {
            CodedError::abort('registration_spa_required', 403, 'Complete registration through the SPA');
        }

        return $next($request);
    }
}
