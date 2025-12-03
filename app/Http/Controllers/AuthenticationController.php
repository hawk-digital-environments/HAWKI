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
        protected AuthServiceInterface $authService,
        protected LanguageController $languageController,
        private readonly LoggerInterface $logger,
    ) {}

    public function handleLogin(Request $request): Response
    {
        /**
         * Based on the actual AuthService implementation,
         * we may need to set credentials before calling authenticate.
         * This closure handles that logic.
         * It will always return either AuthenticatedUserInfo or a Response.
         *
         * @return AuthenticatedUserInfo|Response
         */
        $callAuthenticate = function () use ($request) {
            if ($this->authService instanceof AuthServiceWithCredentialsInterface) {
                if (! $request->isMethod('POST')) {
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
         *
         * @param  string  $url
         * @return RedirectResponse|JsonResponse
         */
        $respond = static function (string $url) use ($authHasForm) {
            if (! $authHasForm) {
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

            $this->logger->info('LOGIN: '.$authenticateResult->username);

            $user = User::where('username', $authenticateResult->username)
                ->where('isRemoved', 0)
                ->first();

            if ($user) {
                if ($this->authService instanceof AuthServiceWithPostProcessingInterface) {
                    $postProcessResponse = $this->authService->afterLoginWithUser($user, $request, $authenticateResult);
                    if ($postProcessResponse !== null) {
                        return $postProcessResponse;
                    }
                }

                Auth::login($user);

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
                'authenticatedUserInfo' => json_encode($authenticateResult),
            ]);

            return $respond('/register');
        } catch (\Throwable $e) {
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

    // / Initiate handshake process
    // / sends back the user keychain.
    // / keychain sync will be done on the frontend side (check encryption.js)
    public function handshake(Request $request)
    {

        $userInfo = Auth::user();

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService)->render();

        $profileService = new ProfileService;
        $keychainData = $profileService->fetchUserKeychain();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'handshake') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('translation', 'settingsPanel', 'userInfo', 'keychainData', 'activeOverlay'));

    }

    // / Redirect user to registration page
    public function register(Request $request)
    {

        if (Auth::check()) {
            // The user is logged in, redirect to /chat
            return redirect('/handshake');
        }

        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService)->render();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'register') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'register');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.register', compact('translation', 'settingsPanel', 'userInfo', 'activeOverlay'));
    }

    // / Setup User
    // / Create backup for userkeychain on the DB
    public function completeRegistration(Request $request, AnnouncementService $announcementService)
    {
        try {
            // Validate input data
            $validatedData = $request->validate([
                'publicKey' => 'required|string',
                'keychain' => 'required|string',
                'KCIV' => 'required|string',
                'KCTAG' => 'required|string',
                'backupHash' => 'nullable|string',
            ]);

            // Retrieve user info from session
            $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);

            // Process user info
            $username = $userInfo['username'] ?? null;
            $name = $userInfo['name'] ?? null;
            $email = $userInfo['email'] ?? null;
            $employeetype = $userInfo['employeetype'] ?? null;

            $avatarId = $validatedData['avatar_id'] ?? '';

            // Check if user already exists to preserve their auth_type
            $existingUser = User::where('username', $username)->first();

            // Determine auth type: preserve existing or infer from authentication service
            if ($existingUser) {
                // Preserve existing auth_type - it must never be changed
                $authType = $existingUser->auth_type;

                $this->logger->info('Completing registration for existing user - preserving auth_type', [
                    'username' => $username,
                    'auth_type' => $authType,
                    'existing_user_id' => $existingUser->id,
                ]);
            } else {
                // New user: determine auth type from authentication service class name
                $authServiceClass = class_basename($this->authService);
                $authType = match (true) {
                    str_contains($authServiceClass, 'Ldap') => 'ldap',
                    str_contains($authServiceClass, 'Oidc') || str_contains($authServiceClass, 'OpenId') => 'oidc',
                    str_contains($authServiceClass, 'Shibboleth') => 'shibboleth',
                    str_contains($authServiceClass, 'Local') => 'local',
                    default => 'ldap',
                };

                $this->logger->info('Completing registration for new user - setting initial auth_type', [
                    'username' => $username,
                    'auth_type' => $authType,
                    'auth_service' => $authServiceClass,
                ]);
            }

            // Prepare user data for update/creation
            $userData = [
                'name' => $name,
                'email' => $email,
                'employeetype' => $employeetype,
                'publicKey' => $validatedData['publicKey'],
                'avatar_id' => $avatarId,
                'isRemoved' => false,
                'auth_type' => $authType,
            ];

            // Handle approval logic based on auth type
            if ($authType === 'local') {
                // Local users: respect local_needapproval config and existing approval status
                if ($existingUser && $existingUser->approval !== null) {
                    // Preserve existing approval status for local users
                    $userData['approval'] = $existingUser->approval;
                } else {
                    // New local user: set approval based on config
                    $localNeedsApproval = config('auth.local_needapproval', true);
                    $userData['approval'] = ! $localNeedsApproval; // If approval needed: false, else: true
                }
            } else {
                // External auth users (LDAP/OIDC/Shibboleth) are always auto-approved after registration
                $userData['approval'] = true;
            }

            // Update or create the user
            $user = User::updateOrCreate(
                ['username' => $username],
                $userData
            );

            try {
                $policy = $announcementService->fetchLatestPolicy();
                $announcementService->markAnnouncementAsSeen($user, $policy->id);
                $announcementService->markAnnouncementAsAccepted($user, $policy->id);
            } catch (\Throwable) {
            }

            // Update or create the Private User Data
            // Use updateOrCreate to prevent duplicate entries when user re-registers
            PrivateUserData::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'KCIV' => $validatedData['KCIV'],
                    'KCTAG' => $validatedData['KCTAG'],
                    'keychain' => $validatedData['keychain'],
                ]
            );

            // Send appropriate email based on approval status if feature is enabled
            if (config('hawki.send_registration_mails', true)) {
                try {
                    $emailService = app(\App\Services\EmailService::class);

                    // Prepare custom data with backup hash if provided
                    $customData = [];
                    if (isset($validatedData['backupHash'])) {
                        $customData['{{backup_hash}}'] = $validatedData['backupHash'];
                    }

                    // Send different email based on approval status
                    if ($user->approval === true) {
                        // User is approved - send welcome email with backup hash
                        $emailService->sendWelcomeEmail($user, $customData);
                        $this->logger->info('Welcome email sent after registration completion', [
                            'user_id' => $user->id,
                            'username' => $user->username,
                            'email' => $user->email,
                            'approval' => true,
                            'backup_hash_included' => isset($validatedData['backupHash']),
                        ]);
                    } else {
                        // User needs approval - send pending email (no backup hash needed yet)
                        $emailService->sendApprovalPendingEmail($user);
                        $this->logger->info('Approval pending email sent after registration completion', [
                            'user_id' => $user->id,
                            'username' => $user->username,
                            'email' => $user->email,
                            'approval' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to send registration email', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the registration if email fails
                }
            }

            // Log the user in
            Session::put('registration_access', false);
            Auth::login($user);

            return response()->json([
                'success' => true,
                'redirectUri' => '/chat',
                'userData' => $user,
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
