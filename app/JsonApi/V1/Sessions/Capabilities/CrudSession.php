<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions\Capabilities;

use App\Http\Errors\CodedError;
use App\Services\Auth\Exceptions\AuthFailedException;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\AuthCredentials;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Auth\Value\Session;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use LaravelJsonApi\NonEloquent\Capabilities\CrudResource;
use App\Services\System\Values\HawkiInstanceId;

/**
 * CRUD capability of the `sessions` resource — the write path. `create` runs one login
 * attempt through the {@see LoginHandler} and promotes the request's user context to the
 * state the login produced (authenticated user or registering user), so the controller's
 * `created` hook can answer with the connection resource plus the `next` step.
 */
class CrudSession extends CrudResource
{
    public function __construct(
        private readonly LoginHandler  $login,
        private readonly SpaAuthHandoff $handoff,
        private readonly UserContext   $userContext,
        private readonly AuthManager   $auth,
        Request                        $request,
    ) {
        parent::__construct();
        $this->request = $request;
    }

    public function create(array $validatedData): object
    {
        if (!$this->login->requiresCredentials()) {
            CodedError::abort(
                'auth_redirect_required',
                409,
                'Redirect login required',
                'Start sign-in through the redirect link of the auth resource.'
            );
        }

        $this->handoff->discard($this->request);

        try {
            $result = $this->login->handle(
                $this->request,
                new AuthCredentials($validatedData['account'], $validatedData['password'])
            );
        } catch (AuthFailedException $exception) {
            CodedError::abort('invalid_credentials', 401, 'Invalid credentials', 'The supplied credentials are invalid.');
        }

        if ($result->isResponse()) {
            $this->abortForServiceResponse();
        }

        if ($result->nextStep === LoginNextStep::HANDSHAKE) {
            $this->userContext->set(WellKnownUserTypes::USER);
        } else {
            $info = $result->authenticatedUserInfo;
            if ($info === null) {
                throw new \LogicException('A registration login must include authenticated user information.');
            }
            $this->userContext->setRegisteringUser(new RegisteringUser(
                username: $info->username,
                name: $info->displayName,
                email: $info->email,
                employeeType: $info->employeeType,
            ));
            $this->handoff->markSpaRegistration($this->request);
        }

        return new Session(HawkiInstanceId::HAWKI, $result);
    }

    /**
     * The auth service answered with its own response instead of a login step — e.g. a
     * {@see \App\Services\Auth\ChainedAuthService} that fell through to a redirect provider, or
     * a post-processing hook. A JSON:API client cannot follow that response, so the attempt
     * is rejected. Any login state the handler already wrote (an authenticated guard after
     * `afterLoginWithUser`) is rolled back, so the error is not contradicted by the session.
     */
    private function abortForServiceResponse(): never
    {
        $this->auth->guard()->logout();
        $this->request->session()->forget([
            'registration_access',
            'authenticatedUserInfo',
            SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
        ]);

        CodedError::abort(
            'auth_redirect_required',
            409,
            'Redirect login required',
            'The authentication provider requires a browser redirect. Start sign-in through the redirect link of the auth resource.'
        );
    }
}
