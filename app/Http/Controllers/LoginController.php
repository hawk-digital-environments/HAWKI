<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class LoginController extends Controller
{
    /// Redirect to Login Page
    public function index(LoginHandler $login, Request $request)
    {
        Session::put('registration_access', false);

        if (Auth::check()) {
            return redirect('/handshake');
        }

        $showLoginForm = $login->requiresCredentials();
        $showRedirectLogin = $login->supportsRedirect();
        // Read authentication forms
        $authForms = View::make('partials.login.authForms', compact('showLoginForm', 'showRedirectLogin'))->render();

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
