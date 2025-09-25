<?php

namespace App\Http\Controllers;

use App\Events\GuestAccountCreated;
use App\Mail\OTPMail;
use App\Models\PrivateUserData;
use App\Models\User;
use App\Services\Announcements\AnnouncementService;
use App\Services\Auth\LdapService;
use App\Services\Auth\LocalAuthService;
use App\Services\Auth\OidcService;
use App\Services\Auth\ShibbolethService;
use App\Services\Auth\TestAuthService;
use App\Services\Profile\ProfileService;
use App\Services\System\SettingsService;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    protected $authMethod;

    protected $ldapService;

    protected $shibbolethService;

    protected $oidcService;

    protected $testAuthService;

    protected $localAuthService;

    protected $languageController;

    public function __construct(LdapService $ldapService, ShibbolethService $shibbolethService, OidcService $oidcService, TestAuthService $testAuthService, LocalAuthService $localAuthService, LanguageController $languageController)
    {
        $this->authMethod = config('auth.authentication_method', 'LDAP');
        $this->ldapService = $ldapService;
        $this->shibbolethService = $shibbolethService;
        $this->oidcService = $oidcService;
        $this->testAuthService = $testAuthService;
        $this->localAuthService = $localAuthService;

        $this->languageController = $languageController;
    }

    // / User Ldap Service to request user info
    // / Redirect to Handshake or Create Registration Access and redirect to Registration
    public function ldapLogin(Request $request)
    {
        $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = filter_var($request->input('account'), FILTER_UNSAFE_RAW);
        $password = $request->input('password');

        $authenticatedUserInfo = null;
        if (config('test_users')['active']) {
            $authenticatedUserInfo = $this->testAuthService->authenticate($username, $password);
        }

        if (! $authenticatedUserInfo) {
            if ($this->authMethod === 'LDAP') {
                $authenticatedUserInfo = $this->ldapService->authenticate($username, $password);
            }
        }

        // If Login Failed
        if (! $authenticatedUserInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Login Failed!',
            ]);
        }

        Log::info('LOGIN: '.$authenticatedUserInfo['username']);
        $username = $authenticatedUserInfo['username'];
        $user = User::where('username', $username)->first();

        // If first time on HAWKI
        if ($user && $user->isRemoved === 0) {
            Auth::login($user);

            return response()->json([
                'success' => true,
                'redirectUri' => '/handshake',
            ]);
        } else {

            Session::put('registration_access', true);
            Session::put('authenticatedUserInfo', json_encode($authenticatedUserInfo));

            return response()->json([
                'success' => true,
                'redirectUri' => '/register',
            ]);
        }
    }

    public function shibbolethLogin(Request $request)
    {
        try {
            $authenticatedUserInfo = $this->shibbolethService->authenticate($request);

            if (! $authenticatedUserInfo) {
                return response()->json(['error' => 'Login Failed!'], 401);
            }

            Log::info('LOGIN: '.$authenticatedUserInfo['username']);

            $user = User::where('username', $authenticatedUserInfo['username'])->first();

            if ($user && $user->isRemoved === 0) {
                Auth::login($user);

                return redirect('/handshake');
            }

            Session::put('registration_access', true);
            Session::put('authenticatedUserInfo', json_encode($authenticatedUserInfo));

            return redirect('/register');

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function openIDLogin()
    {
        try {
            $authenticatedUserInfo = $this->oidcService->authenticate();

            if (! $authenticatedUserInfo) {
                return response()->json(['error' => 'Login Failed!'], 401);
            }

            Log::info('LOGIN: '.$authenticatedUserInfo['username']);

            $user = User::where('username', $authenticatedUserInfo['username'])->first();

            if ($user && $user->isRemoved === 0) {
                Auth::login($user);

                return redirect('/handshake');
            }

            Session::put('registration_access', true);
            Session::put('authenticatedUserInfo', json_encode($authenticatedUserInfo));

            return redirect('/register');

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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

        // Get passkey secret from config
        $passkeySecret = config('auth.passkey_secret', 'default_secret');

        $profileService = new ProfileService;
        $keychainData = $profileService->fetchUserKeychain();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'handshake') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('translation', 'settingsPanel', 'userInfo', 'keychainData', 'activeOverlay', 'passkeySecret'));

    }

    // / Redirect user to registration page
    public function register(Request $request)
    {

        if (Auth::check()) {
            // The user is logged in, redirect to /chat
            return redirect('/handshake');
        }

        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
        $isFirstLoginLocalUser = Session::get('first_login_local_user', false);

        // Check if local user needs password reset and approval
        $needsPasswordReset = false;
        $needsApproval = false;

        if ($isFirstLoginLocalUser && isset($userInfo['username'])) {
            $user = User::where('username', $userInfo['username'])->first();

            if ($user) {
                $needsPasswordReset = $user->reset_pw;

                // Calculate approval logic for local users
                if ($user->auth_type === 'local') {
                    $localNeedsApproval = config('auth.local_needapproval', true);
                    $needsApproval = ! $user->approval && $localNeedsApproval;
                }
            }
        }

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService)->render();

        // Get passkey secret from config
        $passkeySecret = config('auth.passkey_secret', 'default_secret');
        $passkeyMethod = config('auth.passkey_method', 'user');

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'register') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'register');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.register', compact('translation', 'settingsPanel', 'userInfo', 'activeOverlay', 'passkeySecret', 'passkeyMethod', 'isFirstLoginLocalUser', 'needsPasswordReset', 'needsApproval'));
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
                'newPassword' => 'nullable|string|min:6', // For local users changing password
            ]);

            // Retrieve user info from session
            $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
            $isFirstLoginLocalUser = Session::get('first_login_local_user', false);

            // Process user info
            $username = $userInfo['username'] ?? null;
            $name = $userInfo['name'] ?? null;
            $email = $userInfo['email'] ?? null;
            $employeetype = $userInfo['employeetype'] ?? null;

            $avatarId = $validatedData['avatar_id'] ?? '';

            // Prepare user data for update/creation
            $userData = [
                'name' => $name,
                'email' => $email,
                'employeetype' => $employeetype,
                'publicKey' => $validatedData['publicKey'],
                'avatar_id' => $avatarId,
                'isRemoved' => false,
            ];

            // Handle password update for local users
            if ($isFirstLoginLocalUser && isset($validatedData['newPassword'])) {
                $userData['password'] = Hash::make($validatedData['newPassword']);
                $userData['reset_pw'] = false; // Password has been reset
            }

            // Update or create the local user
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
            PrivateUserData::create(
                [
                    'user_id' => $user->id,
                    'KCIV' => $validatedData['KCIV'],
                    'KCTAG' => $validatedData['KCTAG'],
                    'keychain' => $validatedData['keychain'],
                ]
            );
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
        // Log out the user
        Auth::logout();

        // Invalidate the session (flushes + regenerates token)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear PHPSESSID cookie (optional, Laravel doesn’t use PHPSESSID by default)
        Cookie::queue(Cookie::forget('PHPSESSID'));

        // Redirect depending on authentication method
        $authMethod = env('AUTHENTICATION_METHOD');
        if ($authMethod === 'Shibboleth') {
            $redirectUri = config('shibboleth.logout_path');
        } elseif ($authMethod === 'OIDC') {
            $redirectUri = config('open_id_connect.oidc_logout_path');
        } else {
            $redirectUri = '/login';
        }

        return redirect($redirectUri);
    }

    /**
     * Local user authentication
     * Independent of the main authentication method
     */
    public function localLogin(Request $request)
    {
        $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = filter_var($request->input('account'), FILTER_UNSAFE_RAW);
        $password = $request->input('password');

        try {
            // Authenticate using LocalAuthService
            $authenticatedUserInfo = $this->localAuthService->authenticate($username, $password);

            // If Login Failed
            if (! $authenticatedUserInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login Failed!',
                ]);
            }

            Log::info('LOCAL LOGIN: '.$authenticatedUserInfo['username']);
            $username = $authenticatedUserInfo['username'];
            $user = User::where('username', $username)->first();

            // If user exists and is not removed
            if ($user && $user->isRemoved === 0) {

                // Check if this is a local user who needs to complete registration
                if ($user->auth_type === 'local' && empty($user->publicKey)) {
                    // Local user who needs to complete registration process
                    // This includes both admin-created users and self-service users
                    Session::put('registration_access', true);
                    Session::put('authenticatedUserInfo', json_encode($authenticatedUserInfo));
                    Session::put('first_login_local_user', true);

                    return response()->json([
                        'success' => true,
                        'redirectUri' => '/register',
                    ]);
                }

                // Regular login - user has already completed registration or is not local
                Auth::login($user);

                return response()->json([
                    'success' => true,
                    'redirectUri' => '/handshake',
                ]);
            } else {
                // This should not happen for local users, but handle gracefully
                return response()->json([
                    'success' => false,
                    'message' => 'User account not found or deactivated.',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Local authentication error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Authentication error occurred.',
            ], 500);
        }
    }

    /**
     * Submit guest access request
     * Creates a new local user account with submitted credentials
     */
    public function submitGuestRequest(Request $request)
    {
        // Check if local authentication and self-service are enabled
        if (! config('auth.local_authentication', false) || ! config('auth.local_selfservice', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Guest access request is not available.',
            ], 403);
        }

        // Get available role slugs for validation
        $availableRoles = \Orchid\Platform\Models\Role::pluck('slug')->toArray();
        $rolesList = implode(',', $availableRoles);

        // Validate the request
        $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
                'unique:users,username',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/',
            ],
            'password_confirmation' => 'required|string|same:password',
            'email' => 'required|email|max:255|unique:users,email',
            'employeetype' => "required|string|in:{$rolesList}",
        ], [
            'username.required' => 'Username is required',
            'username.min' => 'Username must be at least 3 characters long',
            'username.regex' => 'Username can only contain letters, numbers, underscores, and hyphens',
            'username.unique' => 'This username is already taken',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters long',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number',
            'password_confirmation.required' => 'Password confirmation is required',
            'password_confirmation.same' => 'Passwords do not match',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email address is already registered',
            'employeetype.required' => 'User group is required',
            'employeetype.in' => 'Please select a valid user group',
        ]);

        try {
            // Create the local user using LocalAuthService
            $userData = [
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'employeetype' => $request->input('employeetype'),
                'password' => $request->input('password'),
                'name' => $request->input('username'), // Use username as name initially
            ];

            // Guest request users don't need to reset their password - they chose it themselves
            $user = $this->localAuthService->createLocalUser($userData, false);

            if ($user) {
                Log::info('Guest request submitted successfully', [
                    'username' => $user->username,
                    'email' => $user->email,
                    'employeetype' => $user->employeetype,
                    'auth_type' => $user->auth_type,
                    'reset_pw' => $user->reset_pw,
                ]);

                // Fire event to notify admins about new guest account
                GuestAccountCreated::dispatch($user);

                return response()->json([
                    'success' => true,
                    'message' => 'Your guest access request has been submitted successfully. You can now log in with your credentials.',
                ]);
            } else {
                throw new \Exception('Failed to create user account');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Guest request submission error: '.$e->getMessage(), [
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }

    /**
     * Send OTP to user's email for passkey alternative authentication
     */
    public function sendOTP(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'email' => 'required|email',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated',
                ], 401);
            }

            // Verify that the email matches the authenticated user
            if ($user->email !== $request->input('email')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email does not match authenticated user',
                ], 403);
            }

            // Generate 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store OTP in session with expiration
            $otpTimeout = config('auth.passkey_otp_timeout', 300); // 5 minutes default
            Session::put('otp_code', $otp);
            Session::put('otp_expires_at', now()->addSeconds($otpTimeout));
            Session::put('otp_user_id', $user->id);

            // Send OTP email
            try {
                Mail::to($user->email)->send(new OTPMail($user, $otp));
                Log::info('OTP email sent successfully to user: '.$user->username);
            } catch (\Exception $mailException) {
                Log::warning('Failed to send OTP email, but continuing with OTP generation', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage(),
                ]);
                // Continue execution even if email fails - OTP is still valid
            }

            // Log OTP for development
            if (app()->environment('local')) {
                Log::info('OTP Code generated for user: '.$user->username.' - Code: '.$otp);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your email',
                'debug_otp' => app()->environment('local') ? $otp : null, // Only in local environment
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('OTP sending error: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify OTP code for passkey alternative authentication
     */
    public function verifyOTP(Request $request)
    {
        try {
            $request->validate([
                'otp' => 'required|string|size:6',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated',
                ], 401);
            }

            $inputOtp = $request->input('otp');
            $sessionOtp = Session::get('otp_code');
            $expiresAt = Session::get('otp_expires_at');
            $sessionUserId = Session::get('otp_user_id');

            // Check if OTP exists in session
            if (! $sessionOtp || ! $expiresAt || ! $sessionUserId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No OTP found or session expired',
                ], 400);
            }

            // Check if OTP has expired
            if (now()->gt($expiresAt)) {
                // Clear expired OTP from session
                Session::forget(['otp_code', 'otp_expires_at', 'otp_user_id']);

                return response()->json([
                    'success' => false,
                    'error' => 'OTP has expired',
                ], 400);
            }

            // Check if user matches
            if ($sessionUserId !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid session',
                ], 403);
            }

            // Verify OTP
            if ($inputOtp !== $sessionOtp) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid OTP code',
                ], 400);
            }

            // OTP is valid - clear from session
            Session::forget(['otp_code', 'otp_expires_at', 'otp_user_id']);

            Log::info('OTP verified successfully for user: '.$user->username);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('OTP verification error: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to verify OTP. Please try again.',
            ], 500);
        }
    }
}
