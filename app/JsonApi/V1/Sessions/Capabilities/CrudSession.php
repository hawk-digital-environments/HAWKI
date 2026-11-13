<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions\Capabilities;

use App\Http\Errors\CodedError;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\AuthCredentials;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Auth\Value\Session;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Http\Request;
use LaravelJsonApi\NonEloquent\Capabilities\CrudResource;

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
        private readonly Request       $request,
    ) {
        parent::__construct();
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

        return new Session('hawki', $result);
    }
}
