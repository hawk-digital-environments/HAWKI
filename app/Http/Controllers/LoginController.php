<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\LanguageController;



class LoginController extends Controller
{
    protected $languageController;

    // Inject LanguageController instance
    public function __construct(LanguageController $languageController)
    {
        $this->languageController = $languageController;
    }

    /// Redirect to Login Page
    public function index(){
        Session::put('registration_access', false);

        if(Auth::check()){
            return redirect('/handshake');
        }


        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsController())->initialize();

        $authenticationMethod = config('auth.authentication_method', 'LDAP');
        $localUsersActive = config('auth.local_authentication', false);
        $localSelfserviceActive = config('auth.local_selfservice', false);
        
        // Get available roles for the guest registration form
        $availableRoles = [];
        if ($localUsersActive && $localSelfserviceActive) {
            $availableRoles = \App\Models\Role::where('selfassign', true)->orderBy('name')->get();
        }

       // Read authentication forms
        $authForms = View::make('partials.login.authForms', compact('translation', 'authenticationMethod', 'localUsersActive', 'localSelfserviceActive', 'availableRoles'))->render();

        // Initialize settings panel
        $settingsPanel = (new SettingsController())->initialize($translation);


        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'login'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'login');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('layouts.login', compact('translation', 
                                            'authForms', 
                                            'settingsPanel', 
                                            'activeOverlay'));
    }


}
