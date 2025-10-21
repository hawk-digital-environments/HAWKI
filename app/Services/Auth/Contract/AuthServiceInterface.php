<?php
declare(strict_types=1);


namespace App\Services\Auth\Contract;


use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface AuthServiceInterface
{
    /**
     * MUST attempt to authenticate the user based on the current context (e.g., session, token, etc.).
     * MAY use the extension of {@see AuthServiceWithCredentialsInterface} to get credentials if applicable.
     * If authentication is successful, it MUST return an instance of {@see AuthenticatedUserInfo}.
     * If authentication fails, with an internal error an exception MUST be thrown.
     * If authentication fails due to user not being authenticated, a {@see Response} with an appropriate
     * redirection or error message MUST be returned.
     *
     * IF {@see AuthServiceWithCredentialsInterface} is used, the flow is as follows:
     * ```
     * $authService->useCredentials($username, $password);
     * $result = $authService->authenticate();
     * $authService->forgetCredentials();
     * ```
     *
     * @param Request $request
     * @return AuthenticatedUserInfo|Response
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response;
}
