<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\PrivateUserData;

use App\Services\Profile\ProfileService;
use App\Services\System\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LanguageController;

use App\Services\Auth\LdapService;
use App\Services\Auth\OidcService;
use App\Services\Auth\ShibbolethService;
use App\Services\Auth\TestAuthService;

use App\Services\Announcements\AnnouncementService;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Cookie;

class AuthenticationController extends Controller
{
    protected $authMethod;

    protected $ldapService;
    protected $shibbolethService;
    protected $oidcService;
    protected $testAuthService;

    protected $languageController;


    public function __construct(LdapService $ldapService, ShibbolethService $shibbolethService , OidcService $oidcService, TestAuthService $testAuthService, LanguageController $languageController)
    {
        $this->authMethod = env('AUTHENTICATION_METHOD');
        $this->ldapService = $ldapService;
        $this->shibbolethService = $shibbolethService;
        $this->oidcService = $oidcService;
        $this->testAuthService = $testAuthService;

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
        if(config('test_users')['active']){
            $authenticatedUserInfo = $this->testAuthService->authenticate($username, $password);
        }

        if(!$authenticatedUserInfo) {
            if($this->authMethod === 'LDAP'){
                $authenticatedUserInfo = $this->ldapService->authenticate($username, $password);
            }
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




    public function openIDLogin()
    {
        try {
            $authenticatedUserInfo = $this->oidcService->authenticate();

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
        $settingsPanel = (new SettingsService())->render();

        $profileService = new ProfileService();
        $keychainData = $profileService->fetchUserKeychain();

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'handshake'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'handshake');


        // Pass translation, authenticationMethod, and authForms to the view
        return view('partials.gateway.handshake', compact('translation', 'settingsPanel', 'userInfo', 'keychainData', 'activeOverlay'));

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
        $settingsPanel = (new SettingsService())->render();

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'register'){
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

}
