<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Errors\CodedError;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\LogoutHandler;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\AuthCredentials;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Frontend\Connection\ConnectionFactory;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AuthController extends Controller
{
    use Actions\FetchOne;

    public function login(
        Request $request,
        LoginHandler $loginHandler,
        ConnectionFactory $connections,
        UserContext $userContext,
        SpaAuthHandoff $handoff,
    ) {
        if (!$loginHandler->requiresCredentials()) {
            CodedError::abort('auth_redirect_required', 409, 'Redirect login required', 'Start sign-in through /auth/redirect.');
        }
        $credentials = $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $loginHandler->handle(
                $request,
                new AuthCredentials($credentials['account'], $credentials['password'])
            );
        } catch (AuthFailedException $exception) {
            CodedError::abort('invalid_credentials', 401, 'Invalid credentials', $exception->getMessage());
        }

        if ($result->isResponse()) {
            return $result->response;
        }

        if ($result->nextStep === LoginNextStep::HANDSHAKE) {
            $userContext->set(WellKnownUserTypes::USER);
        } else {
            $info = $result->authenticatedUserInfo;
            if ($info === null) {
                throw new \LogicException('A registration login must include authenticated user information.');
            }
            $userContext->setRegisteringUser(new RegisteringUser(
                username: $info->username,
                name: $info->displayName,
                email: $info->email,
                employeeType: $info->employeeType,
            ));
            $handoff->markSpaRegistration($request);
        }

        return DataResponse::make($connections->createHawkiConnection())
            ->withMeta(['next' => $result->nextStep->value]);
    }

    public function logout(Request $request, LogoutHandler $logoutHandler): JsonResponse
    {
        return response()
            ->json(['redirect_url' => $logoutHandler->handle($request)])
            ->header('Content-Type', 'application/vnd.api+json');
    }
}
