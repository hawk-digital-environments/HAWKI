<?php

namespace App\Http\Controllers;

use App\Services\System\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class LoginController extends Controller
{
    protected $languageController;

    // Inject LanguageController instance
    public function __construct(LanguageController $languageController)
    {
        $this->languageController = $languageController;
    }

    // / Redirect to Login Page
    public function index()
    {
        Session::put('registration_access', false);

        if (Auth::check()) {
            return redirect('/handshake');
        }

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService)->render();

        $authenticationMethod = config('auth.authentication_method', 'LDAP');

        // Local authentication settings - load directly from database if config is not set
        $localUsersActive = config('auth.local_authentication', false);
        $localSelfserviceActive = config('auth.local_selfservice', false);

        // Fallback: Load from database if config values are not set
        // if (!$localUsersActive || !$localSelfserviceActive) {
        //    $dbSettings = \DB::table('app_settings')
        //        ->whereIn('key', ['auth_local_authentication', 'auth_local_selfservice'])
        //        ->pluck('value', 'key');
        //
        //    $localUsersActive = ($dbSettings['auth_local_authentication'] ?? 'false') === 'true';
        //    $localSelfserviceActive = ($dbSettings['auth_local_selfservice'] ?? 'false') === 'true';
        // }

        // Get available roles for guest registration
        $availableRoles = [];
        if ($localSelfserviceActive) {
            $availableRoles = \Orchid\Platform\Models\Role::where('selfassign', true)->get();
        }

        // Read authentication forms with local auth variables
        $authForms = View::make('partials.login.authForms', compact(
            'translation',
            'authenticationMethod',
            'localUsersActive',
            'localSelfserviceActive',
            'availableRoles'
        ))->render();

        // Initialize settings panel
        $settingsPanel = (new SettingsService)->render();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'login') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'login');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('layouts.login', compact('translation',
            'authForms',
            'settingsPanel',
            'activeOverlay',
            'authenticationMethod'));
    }
}
