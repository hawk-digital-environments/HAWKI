<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\PrivateUserData;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LocalizationController;


use App\Services\Auth\LdapService;
use App\Services\Auth\OidcService;
use App\Services\Auth\ShibbolethService;
use App\Services\Auth\LocalAuthService;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    protected $authMethod;

    protected $ldapService;
    protected $shibbolethService;
    protected $oidcService;
    protected $localAuthService;

    protected $languageController;


    public function __construct(LdapService $ldapService, ShibbolethService $shibbolethService , OidcService $oidcService, LocalAuthService $localAuthService, LanguageController $languageController)
    {
        $this->authMethod = config('auth.authentication_method', 'LDAP');
        $this->ldapService = $ldapService;
        $this->shibbolethService = $shibbolethService;
        $this->oidcService = $oidcService;
        $this->localAuthService = $localAuthService;

        $this->languageController = $languageController;
    }



    /// User Ldap Service to request user info
    /// Redirect to Handshake or Create Registration Access and redirect to Registration
    public function ldapLogin(Request $request)
    {
        $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = filter_var($request->input('account'), FILTER_UNSAFE_RAW);
        $password = $request->input('password');

        $authenticatedUserInfo = null;
        
        // Use the configured authentication method
        if($this->authMethod === 'LDAP'){
            $authenticatedUserInfo = $this->ldapService->authenticate($username, $password);
        }

        // If Login Failed
        if (!$authenticatedUserInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Login Failed!',
            ]);
        }

        Log::info('LOGIN: ' . $authenticatedUserInfo['username']);
        $username = $authenticatedUserInfo['username'];
        $user = User::where('username', $username)->first();

        

        $redirectUri;
        // If first time on HAWKI
        if($user && $user->isRemoved === 0){
            Auth::login($user);

            return response()->json([
                'success' => true,
                'redirectUri' => '/handshake',
            ]);
        }
        else{

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
    
            if (!$authenticatedUserInfo) {
                return response()->json(['error' => 'Login Failed!'], 401);
            }
    
            Log::info('LOGIN: ' . $authenticatedUserInfo['username']);
    
            $user = User::where('username', $authenticatedUserInfo['username'])->first();
    
            if($user && $user->isRemoved === 0){
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




    public function openIDLogin(Request $request)
    {
        try {
            $authenticatedUserInfo = $this->oidcService->authenticate($request);
    
            if (!$authenticatedUserInfo) {
                return response()->json(['error' => 'Login Failed!'], 401);
            }
    
            Log::info('LOGIN: ' . $authenticatedUserInfo['username']);
    
            $user = User::where('username', $authenticatedUserInfo['username'])->first();
    
            if($user && $user->isRemoved === 0){
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



    /// Initiate handshake process
    /// sends back the user keychain.
    /// keychain sync will be done on the frontend side (check encryption.js)
    public function handshake(Request $request){
        
        $userInfo = Auth::user();

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsController())->initialize();

        $cryptoController = new EncryptionController();
        $keychainData = $cryptoController->fetchUserKeychain();
        
        // Get passkey secret from config
        $passkeySecret = config('auth.passkey_secret');
        
        $settingsPanel = (new SettingsController())->initialize($translation);

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'handshake'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');


        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('translation', 'settingsPanel', 'userInfo', 'keychainData', 'activeOverlay', 'passkeySecret'));
  
    }


    /// Redirect user to registration page
    public function register(Request $request){

        if (Auth::check()) {
            // The user is logged in, redirect to /chat
            return redirect('/handshake');
        }

        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
        $isFirstLoginLocalUser = Session::get('first_login_local_user', false);
        
        // Check if local user needs password reset
        $needsPasswordReset = false;
        if ($isFirstLoginLocalUser && isset($userInfo['username'])) {
            $user = User::where('username', $userInfo['username'])->first();
            $needsPasswordReset = $user && $user->reset_pw;
        }


        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsController())->initialize();
        
        // Hole die lokalisierten Texte vom LocalizationController
        $localizationController = new LocalizationController();
        $localizedTexts = $localizationController->getAllLocalizedContent();
        
        // Get passkey secret from config
        $passkeySecret = config('auth.passkey_secret');
        
        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'register'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'register');


        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.register', compact('translation', 'settingsPanel', 'userInfo', 'activeOverlay', 'localizedTexts', 'passkeySecret', 'isFirstLoginLocalUser', 'needsPasswordReset'));
    }



    /// Setup User
    /// Create backup for userkeychain on the DB
    public function completeRegistration(Request $request)
    {
        try {
            // Log incoming data for debugging
            Log::info('completeRegistration called', [
                'request_data' => $request->all(),
                'session_first_login_local_user' => Session::get('first_login_local_user', false)
            ]);
            
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
            $permissions = $userInfo['permissions'] ?? null;

    
            $avatarId = $validatedData['avatar_id'] ?? '';

            // Prepare data for update/create
            $userData = [
                'name' => $name,
                'email' => $email,
                'employeetype' => $employeetype,
                'publicKey' => $validatedData['publicKey'],
                'avatar_id' => $avatarId,
                'isRemoved' => false,
                'permissions' => $permissions,
            ];

            // For local users on first login, update password if provided and needed
            if ($isFirstLoginLocalUser && !empty($validatedData['newPassword'])) {
                // Check if user needs password reset
                $currentUser = User::where('username', $username)->first();
                if ($currentUser && $currentUser->reset_pw) {
                    Log::info('Setting new password for local user who needs reset', [
                        'username' => $username,
                        'newPassword_length' => strlen($validatedData['newPassword'])
                    ]);
                    $userData['password'] = $validatedData['newPassword']; // Will be auto-hashed
                    $userData['reset_pw'] = false; // Password has been reset
                } else {
                    Log::info('Password change skipped - user does not need reset', [
                        'username' => $username
                    ]);
                }
                Session::forget('first_login_local_user'); // Clear the flag
            }

            // Update or create the local user
            $user = User::updateOrCreate(
                ['username' => $username],
                $userData
            );
    
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
            // error_log('Validation Error: ' . json_encode($e->errors()));
    
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);  // Return HTTP 422 Unprocessable Entity
        }
    }
    
    public function logout(Request $request)
    {
        // Unset all session variables
        Session::flush();

        // Regenerate session ID
        Session::regenerate();

        // Remove PHPSESSID cookie
        if ($request->hasCookie('PHPSESSID')) {
            $cookie = cookie('PHPSESSID', '', time() - 3600);
            Cookie::queue($cookie);
        }

        // Destroy the session
        Session::invalidate();

        // Determine the logout redirect URI based on the authentication method
        $authMethod = config('auth.authentication_method', 'LDAP');
        if ($authMethod === 'Shibboleth') {
            $redirectUri = config('shibboleth.logout_path');
        } elseif ($authMethod === 'OIDC') {
            $redirectUri = config('open_id_connect.oidc_logout_path');
        } else {
            $redirectUri = '/login';
        }

        // Redirect to the appropriate logout URI
        return redirect($redirectUri);
    }

    /**
     * Verify the provided OTP
     */
    public function verifyOTP(Request $request)
    {
        try {
            $providedOTP = $request->input('otp');
            
            if (!$providedOTP) {
                return response()->json([
                    'success' => false,
                    'error' => 'Log-In Code is required'
                ], 400);
            }

            $storedOTP = Session::get('otp_code');
            $otpEmail = Session::get('otp_email');
            $expiresAt = Session::get('otp_expires_at');

            // Check if OTP exists
            if (!$storedOTP) {
                return response()->json([
                    'success' => false,
                    'error' => 'No Log-In Code found. Please request a new one.'
                ], 400);
            }

            // Check if OTP is expired
            if (!$expiresAt || now()->isAfter($expiresAt)) {
                // Clear expired OTP
                Session::forget(['otp_code', 'otp_email', 'otp_expires_at']);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Log-In Code has expired. Please request a new one.'
                ], 400);
            }

            // Verify OTP
            if ($providedOTP === $storedOTP) {
                // Clear OTP after successful verification
                Session::forget(['otp_code', 'otp_email', 'otp_expires_at']);
                
                Log::info('Log-In Code successfully verified for email: ' . $otpEmail);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Log-In Code successfully verified',
                    'redirect_url' => '/chat'
                ]);
            } else {
                Log::warning('Invalid Log-In Code attempt for email: ' . $otpEmail);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid Log-In Code. Please try again.'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Log-In Code verification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error during Log-In Code verification'
            ], 500);
        }
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
            if (!$authenticatedUserInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login Failed!',
                ]);
            }

            Log::info('LOCAL LOGIN: ' . $authenticatedUserInfo['username']);
            $username = $authenticatedUserInfo['username'];
            $user = User::where('username', $username)->first();

            // If user exists and is not removed
            if($user && $user->isRemoved === 0){
                
                // Check if this is a local user who needs to complete registration
                if($user->auth_type === 'local' && empty($user->publicKey)) {
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
            }
            else {
                // This should not happen for local users, but handle gracefully
                return response()->json([
                    'success' => false,
                    'message' => 'User account not found or deactivated.',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Local authentication error: ' . $e->getMessage());
            
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
        // Get available role slugs for validation
        $availableRoles = \App\Models\Role::pluck('slug')->toArray();
        $rolesList = implode(',', $availableRoles);
        
        // Validate the request
        $request->validate([
            'username' => [
                'required', 
                'string', 
                'min:3', 
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
                'unique:users,username'
            ],
            'password' => [
                'required', 
                'string', 
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/'
            ],
            'password_confirmation' => 'required|string|same:password',
            'email' => 'required|email|max:255|unique:users,email',
            'employeetype' => "required|string|in:{$rolesList}"
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
            'employeetype.in' => 'Please select a valid user group'
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
                    'reset_pw' => $user->reset_pw
                ]);

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
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Guest request submission error: ' . $e->getMessage(), [
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }
    
}
