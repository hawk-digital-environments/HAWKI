<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\SpaAuthPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToSpaAuth
{
    public function __construct(private readonly SpaAuthHandoff $handoff)
    {
    }

    public function handle(Request $request, Closure $next, string $page): Response
    {
        $step = SpaAuthPage::from($page);
        $enabled = $step === SpaAuthPage::REGISTER
            ? $this->handoff->requiresSpaRegistration($request)
            : $this->handoff->isEnabled();

        if ($enabled) {
            return redirect($this->handoff->urlFor($step));
        }
        if ($step === SpaAuthPage::LOGIN) {
            $this->handoff->discard($request);
        }

        return $next($request);
    }
}
