<?php

namespace App\Http\Controllers;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
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

        $showLoginForm = $authService instanceof AuthServiceWithCredentialsInterface;
        // Read authentication forms
        $authForms = View::make('partials.login.authForms', compact('showLoginForm'))->render();

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'login') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'login');

        // Pass translation, authenticationMethod, and authForms to the view
        return view('layouts.login', compact(
            'authForms',
            'activeOverlay'));
    }
}
