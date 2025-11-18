<?php

namespace App\Http\Controllers;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\System\SettingsService;
use Illuminate\Http\Request;
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

    /// Redirect to Login Page
    public function index(AuthServiceInterface $authService, Request $request)
    {
        Session::put('registration_access', false);

        if (Auth::check()) {
            return redirect('/handshake');
        }

        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService())->render();

        $showLoginForm = $authService instanceof AuthServiceWithCredentialsInterface;
        // @todo we probably have to adjust this, so $showLoginForm gets updated.
        $localUsersActive = config('auth.local_authentication', false);
        $localSelfserviceActive = config('auth.local_selfservice', false);

        // Read authentication forms
        $authForms = View::make('partials.login.authForms', compact('translation', 'showLoginForm'))->render();

        // Initialize settings panel
        $settingsPanel = (new SettingsService())->render();

        // Get available roles for guest registration
        $availableRoles = [];
        if ($localSelfserviceActive) {
            $availableRoles = \Orchid\Platform\Models\Role::where('selfassign', true)->get();
        }

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
            'localUsersActive',
            'localSelfserviceActive',
            'availableRoles'
        ));
    }
}
