<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\SpaAuthPage;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectLoginController extends Controller
{
    public function __invoke(Request $request, LoginHandler $login, SpaAuthHandoff $handoff, LoggerInterface $logger): Response
    {
        if ($login->requiresCredentials()) {
            return redirect($handoff->urlFor(SpaAuthPage::LOGIN));
        }

        $next = $request->query('next');
        $handoff->start($request, is_string($next) ? $next : null);

        try {
            $result = $login->handle($request);

            return $result->isResponse()
                ? $result->response
                : redirect($handoff->completeSuccess($request, $result->nextStep));
        } catch (Throwable $exception) {
            $logger->warning('Failed redirect login attempt', ['exception' => $exception]);

            return redirect($handoff->completeFailure(
                $request,
                $exception instanceof AuthFailedException ? 'invalid_credentials' : 'provider_failed'
            ));
        }
    }
}
