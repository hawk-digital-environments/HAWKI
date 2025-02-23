<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class ShibbolethService
{
    public function authenticate(Request $request)
    {
        // Regenerate CSRF token
        $request->session()->regenerateToken();

        if (!empty($_SERVER['REMOTE_USER'])) {
            $username = $_SERVER['REMOTE_USER'];

            // Retrieve other necessary attributes from Shibboleth
            $email = $_SERVER['mail'] ?? "{$username}@domain.com";
            $name = $_SERVER['displayName'] ?? $username;

            // Find or create the user in the local database
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name, 
                    'email' => $email, 
                    'password' => Hash::make(str_random(16))
                ]
            );

            // Log the user in using Laravel's Auth facade
            Auth::login($user);

            // Regenerate session ID to prevent session fixation attacks
            Session::regenerate();

            return redirect('/chat');
        } else {
            // Redirect to Shibboleth login page
            $loginPath = env('SHIBBOLETH_LOGIN_PATH');
            $loginPage = env('SHIBBOLETH_LOGIN_PAGE');
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
            $shibLogin = "{$scheme}://{$_SERVER['HTTP_HOST']}/{$loginPath}{$loginPage}";

            return redirect($shibLogin);
        }
    }
}
