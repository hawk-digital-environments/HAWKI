<?php

namespace App\Http\Controllers;


use App\Models\PrivateUserData;
use App\Models\User;
use App\Services\Announcements\AnnouncementService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Contract\AuthServiceWithPostProcessingInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Services\Profile\ProfileService;
use App\Services\System\SettingsService;
use Cookie;
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
    )
    {
    }

    public function handleLogin(Request $request): Response
    {
        /**
         * Based on the actual AuthService implementation,
         * we may need to set credentials before calling authenticate.
         * This closure handles that logic.
         * It will always return either AuthenticatedUserInfo or a Response.
         * @return AuthenticatedUserInfo|Response
         */
        $callAuthenticate = function () use ($request) {
            if ($this->authService instanceof AuthServiceWithCredentialsInterface) {
                if (!$request->isMethod('POST')) {
                    throw new AuthFailedException('Login must be performed via POST method.', 400);
                }
                try {
                    $credentials = $request->validate([
                        'account' => 'required|string',
                        'password' => 'required|string',
                    ]);

                    $this->authService->useCredentials(
                        filter_var($credentials['account'], FILTER_UNSAFE_RAW),
                        $credentials['password']
                    );

                    return $this->authService->authenticate($request);
                } catch (ValidationException $e) {
                    throw new AuthFailedException('Username and password are required for login.', 400, $e);
                } finally {
                    $this->authService->forgetCredentials();
                }
            }

            return $this->authService->authenticate($request);
        };

        $authHasForm = $this->authService instanceof AuthServiceWithCredentialsInterface;

        /**
         * A small helper to respond according to request method
         * Handles both GET (redirect) and POST (JSON) requests
         * This is required, because some authentication methods (e.g. Shibboleth, OIDC)
         * initiate login via GET requests and expect a redirect response.
         * @param string $url
         * @return RedirectResponse|JsonResponse
         */
        $respond = static function (string $url) use ($authHasForm) {
            if (!$authHasForm) {
                return redirect($url);
            }

            return response()->json([
                'success' => true,
                'redirectUri' => $url,
            ]);
        };

        try {
            $authenticateResult = $callAuthenticate();

            if ($authenticateResult instanceof Response) {
                return $authenticateResult;
            }

            $this->logger->info('LOGIN: ' . $authenticateResult->username);

            $user = User::where('username', $authenticateResult->username)
                ->where('isRemoved', 0)
                ->first();

            if ($user) {
                Auth::login($user);

                if ($this->authService instanceof AuthServiceWithPostProcessingInterface) {
                    $postProcessResponse = $this->authService->afterLoginWithUser($user, $request);
                    if ($postProcessResponse !== null) {
                        return $postProcessResponse;
                    }
                }

                return $respond('/handshake');
            }

            if ($this->authService instanceof AuthServiceWithPostProcessingInterface) {
                $postProcessResponse = $this->authService->afterLoginWithoutUser($authenticateResult, $request);
                if ($postProcessResponse !== null) {
                    return $postProcessResponse;
                }
            }

            $request->session()->put([
                'registration_access' => true,
                'authenticatedUserInfo' => json_encode($authenticateResult)
            ]);

            return $respond('/register');
        } catch (\Throwable $e) {
            $this->logger->error('Authentication failed', ['exception' => $e]);

            $error = $e instanceof AuthFailedException ? $e->getMessage() : 'An unexpected error occurred during authentication.';

            if ($authHasForm) {
                // Tell the form that the login failed...
                return response()->json([
                    'success' => false,
                    'error' => $error,
                    'message' => 'Login Failed!',
                ]);
            }

            // Redirect back to login with error message
            return redirect('/login')->withErrors(['login_error' => $error]);
        }
    }


    /// Initiate handshake process
    /// sends back the user keychain.
    /// keychain sync will be done on the frontend side (check encryption.js)
    public function handshake(Request $request)
    {

        $userInfo = Auth::user();

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService())->render();

        $profileService = new ProfileService();
        $keychainData = $profileService->fetchUserKeychain();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'handshake') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');


        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('translation', 'settingsPanel', 'userInfo', 'keychainData', 'activeOverlay'));

    }


    /// Redirect user to registration page
    public function register(Request $request)
    {

        if (Auth::check()) {
            // The user is logged in, redirect to /chat
            return redirect('/handshake');
        }

        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);


        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService())->render();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'register') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'register');


        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.register', compact('translation', 'settingsPanel', 'userInfo', 'activeOverlay'));
    }



    /// Setup User
    /// Create backup for userkeychain on the DB
    public function completeRegistration(Request $request, AnnouncementService $announcementService)
    {
        try {
            // Validate input data
            $validatedData = $request->validate([
                'publicKey' => 'required|string',
                'keychain' => 'required|string',
                'KCIV' => 'required|string',
                'KCTAG' => 'required|string',
            ]);

            // Retrieve user info from session
            $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);

            // Process user info
            $username = $userInfo['username'] ?? null;
            $name = $userInfo['name'] ?? null;
            $email = $userInfo['email'] ?? null;
            $employeetype = $userInfo['employeetype'] ?? null;

            $avatarId = $validatedData['avatar_id'] ?? '';

            // Update or create the local user
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'employeetype' => $employeetype,
                    'publicKey' => $validatedData['publicKey'],
                    'avatar_id' => $avatarId,
                    'isRemoved' => false
                ]
            );

            try {
                $policy = $announcementService->fetchLatestPolicy();
                $announcementService->markAnnouncementAsSeen($user, $policy->id);
                $announcementService->markAnnouncementAsAccepted($user, $policy->id);
            } catch (\Throwable) {
            }

            // Update or create the Private User Data
            PrivateUserData::create(
                [
                    'user_id' => $user->id,
                    'KCIV' => $validatedData['KCIV'],
                    'KCTAG' => $validatedData['KCTAG'],
                    'keychain' => $validatedData['keychain']
                ]
            );
            // Log the user in
            Session::put('registration_access', false);
            Auth::login($user);

            return response()->json([
                'success' => true,
                'redirectUri' => '/chat',
                'userData' => $user
            ]);

        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function logout(Request $request)
    {
        // First build the redirect response, so we still have all user- and session-data available.
        $response = redirect('/login');
        if ($this->authService instanceof AuthServiceWithLogoutRedirectInterface) {
            $serviceResponse = $this->authService->getLogoutResponse($request);
            if ($serviceResponse !== null) {
                $response = $serviceResponse;
            }
        }

        // Log out the user
        Auth::logout();

        // Invalidate the session (flushes + regenerates token)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear PHPSESSID cookie (optional, Laravel doesn’t use PHPSESSID by default)
        Cookie::queue(Cookie::forget('PHPSESSID'));

        return $response;
    }

}
