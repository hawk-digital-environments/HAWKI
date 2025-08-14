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
use App\Services\Auth\TestAuthService;
use App\Services\Auth\LocalAuthService;

use Illuminate\Support\Facades\Log;


class AuthenticationController extends Controller
{
    protected $authMethod;

    protected $ldapService;
    protected $shibbolethService;
    protected $oidcService;
    protected $testAuthService;
    protected $localAuthService;

    protected $languageController;


    public function __construct(LdapService $ldapService, ShibbolethService $shibbolethService , OidcService $oidcService, TestAuthService $testAuthService, LocalAuthService $localAuthService, LanguageController $languageController)
    {
        $this->authMethod = config('auth.authentication_method', 'LDAP');
        $this->ldapService = $ldapService;
        $this->shibbolethService = $shibbolethService;
        $this->oidcService = $oidcService;
        $this->testAuthService = $testAuthService;
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
        return view('partials.gateway.register', compact('translation', 'settingsPanel', 'userInfo', 'activeOverlay', 'localizedTexts', 'passkeySecret'));
    }



    /// Setup User
    /// Create backup for userkeychain on the DB
    public function completeRegistration(Request $request)
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
            $permissions = $userInfo['permissions'] ?? null;

    
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
                    'isRemoved' => false,
                    'permissions' => $permissions,
                ]
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
    
}
