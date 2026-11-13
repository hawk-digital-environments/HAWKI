<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithPostProcessingInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Value\AuthCredentials;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Auth\Value\LoginResult;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Auth\AuthManager;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @api
 *
 * Runs one login attempt against the configured {@see AuthServiceInterface} and leaves the
 * session in the state the rest of HAWKI expects.
 *
 * This is the single place that knows the login *sequence*, independent of who asked for it:
 *
 * 1. Feed credentials to the auth service, if it wants any, and always forget them again.
 * 2. If the service answers with a response of its own (a redirect to an identity provider),
 *    hand it back untouched.
 * 3. Otherwise look up the local user for the authenticated username.
 *    - Found → log them into the guard, then {@see LoginNextStep::HANDSHAKE}.
 *    - Not found → park the authenticated identity in the session (which is what promotes the
 *      visitor to a *registering user* on the next request), then {@see LoginNextStep::REGISTER}.
 *
 * What it deliberately does *not* know is how to answer an HTTP request. Both callers — the
 * legacy Blade gateway ({@see \App\Http\Controllers\AuthenticationController}) and the JSON:API
 * login action ({@see \App\Http\Controllers\Api\V1\AuthController}) — shape their own response
 * from the returned {@see LoginResult}, which is why the two UIs can disagree about URLs and
 * status codes without this class changing.
 *
 * ```php
 * $result = $loginHandler->handle($request, new AuthCredentials($account, $password));
 * if ($result->isResponse()) {
 *     return $result->response;
 * }
 * // $result->nextStep is HANDSHAKE or REGISTER
 * ```
 */
#[Singleton]
readonly class LoginHandler
{
    public function __construct(
        private AuthServiceInterface $authService,
        private AuthManager          $auth,
        private LoggerInterface      $logger,
        private UserRepository       $userRepository
    )
    {
    }

    /**
     * True when the configured auth service expects a username and password, i.e. when the
     * frontend has to show a login form and {@see handle()} needs credentials passed to it.
     */
    public function requiresCredentials(): bool
    {
        return $this->authService instanceof AuthServiceWithCredentialsInterface;
    }

    /**
     * Performs the login attempt.
     *
     * @param AuthCredentials|null $credentials Required when {@see requiresCredentials()} is
     *                                          true, ignored otherwise.
     * @throws AuthFailedException When authentication fails. The message is user-facing; the
     *                             code carries the HTTP status the auth service suggested.
     */
    public function handle(Request $request, ?AuthCredentials $credentials = null): LoginResult
    {
        $authenticateResult = $this->authenticate($request, $credentials);

        if ($authenticateResult instanceof Response) {
            return LoginResult::forResponse($authenticateResult);
        }

        $session = $request->session();
        $this->auth->guard()->logout();
        $session->forget([
            'registration_access',
            'authenticatedUserInfo',
            SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
        ]);
        $session->regenerate(true);

        $this->logger->info('LOGIN: ' . $authenticateResult->username);

        $user = $this->userRepository->findOneByUsername(
            $authenticateResult->username,
            ScopeOverrides::makeWithForcefullyDisabled('access')
        );

        if ($user) {
            $this->auth->login($user);

            if ($this->authService instanceof AuthServiceWithPostProcessingInterface) {
                $postProcessResponse = $this->authService->afterLoginWithUser($user, $request);
                if ($postProcessResponse !== null) {
                    return LoginResult::forResponse($postProcessResponse);
                }
            }

            return LoginResult::forStep(LoginNextStep::HANDSHAKE, $user, $authenticateResult);
        }

        if ($this->authService instanceof AuthServiceWithPostProcessingInterface) {
            $postProcessResponse = $this->authService->afterLoginWithoutUser($authenticateResult, $request);
            if ($postProcessResponse !== null) {
                return LoginResult::forResponse($postProcessResponse);
            }
        }

        // Read back by SystemContextBootingMiddleware to rebuild a RegisteringUser on the
        // following requests, and consumed by the registration completion endpoint.
        $session->put([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode($authenticateResult)
        ]);

        return LoginResult::forStep(LoginNextStep::REGISTER, authenticatedUserInfo: $authenticateResult);
    }

    /**
     * @throws AuthFailedException
     */
    private function authenticate(Request $request, ?AuthCredentials $credentials): AuthenticatedUserInfo|Response
    {
        if (!$this->authService instanceof AuthServiceWithCredentialsInterface) {
            return $this->authService->authenticate($request);
        }

        if ($credentials === null) {
            throw new AuthFailedException('Username and password are required for login.', 400);
        }

        try {
            $this->authService->useCredentials($credentials->account, $credentials->password);

            return $this->authService->authenticate($request);
        } finally {
            $this->authService->forgetCredentials();
        }
    }
}
