<?php

namespace App\Http\Controllers;


use App\Services\Announcements\AnnouncementService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\LogoutHandler;
use App\Services\Auth\SpaAuthHandoff;
use App\Services\Auth\Value\AuthCredentials;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Auth\Value\SpaAuthPage;
use App\Http\Errors\CodedError;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController extends Controller
{
    public function __construct(
        protected AuthServiceInterface   $authService,
        protected LanguageController     $languageController,
        private readonly LoggerInterface $logger,
        private readonly UserRepository  $userRepository,
        private readonly LoginHandler    $loginHandler,
        private readonly LogoutHandler   $logoutHandler,
        private readonly SpaAuthHandoff  $spaAuthHandoff
    )
    {
    }

    public function handleLogin(Request $request): Response
    {
        if ($this->loginHandler->requiresCredentials()) {
            $this->spaAuthHandoff->discard($request);
        }
        try {
            $credentials = $this->credentialsFromRequest($request);
            $result = $this->loginHandler->handle($request, $credentials);
            if ($result->isResponse()) {
                return $result->response;
            }

            if ($this->spaAuthHandoff->isPending($request)) {
                return redirect($this->spaAuthHandoff->completeSuccess($request, $result->nextStep));
            }

            if ($this->spaAuthHandoff->isEnabled() && $result->nextStep === LoginNextStep::REGISTER) {
                $this->spaAuthHandoff->markSpaRegistration($request);
            }

            return $this->legacyLoginResponse($result->nextStep);
        } catch (\Throwable $e) {
            $error = $e instanceof AuthFailedException ? 'invalid_credentials' : 'provider_failed';

            $this->logger->warning('Failed login attempt', ['exception' => $e]);

            if ($this->spaAuthHandoff->isPending($request)) {
                return redirect($this->spaAuthHandoff->completeFailure($request, $error));
            }

            $error = $e instanceof AuthFailedException
                ? $e->getMessage()
                : 'An unexpected error occurred during authentication.';

            if ($this->loginHandler->requiresCredentials()) {
                return response()->json([
                    'success' => false,
                    'error' => $error,
                    'message' => 'Login Failed!',
                ]);
            }

            return redirect($this->spaAuthHandoff->entryUrlFor(SpaAuthPage::LOGIN))->withErrors(['login_error' => $error]);
        }
    }

    private function credentialsFromRequest(Request $request): ?AuthCredentials
    {
        if (!$this->loginHandler->requiresCredentials()) {
            return null;
        }
        if (!$request->isMethod('POST')) {
            throw new AuthFailedException('Login must be performed via POST method.', 400);
        }

        try {
            $credentials = $request->validate([
                'account' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $exception) {
            throw new AuthFailedException('Username and password are required for login.', 400, $exception);
        }

        return new AuthCredentials(filter_var($credentials['account'], FILTER_UNSAFE_RAW), $credentials['password']);
    }

    private function legacyLoginResponse(LoginNextStep $nextStep): RedirectResponse|JsonResponse
    {
        $url = $this->spaAuthHandoff->entryUrlFor($nextStep);
        if (!$this->loginHandler->requiresCredentials()) {
            return redirect($url);
        }

        return response()->json(['success' => true, 'redirectUri' => $url]);
    }

    /// Initiate handshake process
    /// sends back the user keychain.
    /// keychain sync will be done on the frontend side (check encryption.js)
    public function handshake(Request $request)
    {
        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'handshake') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('activeOverlay'));

    }


    /// Redirect user to registration page
    public function register(Request $request)
    {

        if (Auth::check()) {
            // The user is logged in, redirect to /chat
            return redirect('/handshake');
        }

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'register') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'register');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.register', compact('activeOverlay'));
    }



    /// Setup User
    /// Create backup for userkeychain on the DB
    public function completeRegistration(
        Request             $request,
        AnnouncementService $announcementService,
        AuthManager         $auth
    )
    {
        try {
            if (!$request->getUserContext()->isRegisteringUser()) {
                CodedError::abort('registration_not_in_progress', 403, 'No registration in progress');
            }

            // Retrieve user info from session
            $userInfo = $request->getUserContext()->getRegisteringUser();

            $user = $this->userRepository->completeLegacyRegistration(
                $userInfo->username,
                $userInfo->name,
                $userInfo->email,
                $userInfo->employeeType,
            );

            try {
                $policy = $announcementService->fetchLatestPolicy();
                $announcementService->markAnnouncementAsSeen($user, $policy->id);
                $announcementService->markAnnouncementAsAccepted($user, $policy->id);
            } catch (\Throwable) {
            }

            // Log the user in
            $session = $request->session();
            $session->forget([
                'authenticatedUserInfo',
                'registration_access',
                SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
            ]);
            $auth->login($user);

            return response()->json([
                'success' => true,
                'redirectUri' => '/chat',
                'userData' => $user,
            ])->withHeaders(['X-HAWKI-CSRF-TOKEN' => $request->session()->token()]);

        } catch (RegistrationAlreadyCompletedException) {
            $request->session()->forget([
                'authenticatedUserInfo',
                'registration_access',
                SpaAuthHandoff::SESSION_REGISTRATION_UI_KEY,
            ]);
            return response()->json(['success' => false, 'message' => 'Registration has already been completed.'], 409);
        }
    }

    public function logout(Request $request)
    {
        return redirect($this->logoutHandler->handle($request) ?? $this->spaAuthHandoff->entryUrlFor(SpaAuthPage::LOGIN));
    }

}
