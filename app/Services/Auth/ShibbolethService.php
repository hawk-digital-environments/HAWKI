<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;


class ShibbolethService
{
    /**
     * Authenticates the user via Shibboleth and returns user information if authenticated.
     * If the user is not authenticated, redirects to the Shibboleth login page.
     *
     * @param Request $request The HTTP request instance.
     * @return array|Illuminate\Http\RedirectResponse|Illuminate\Http\JsonResponse User information array if authenticated,
     *                                                                             redirection to the login page if not authenticated,
     *                                                                             or a JSON error response if required attributes are missing or the login path is not set.
     */
    public function authenticate(Request $request)
    {
        // Check if the user is authenticated
        if (!empty($_SERVER['REMOTE_USER'])) {
            // Retrieve configuration variables
            $gnameVar = config('shibboleth.attribute_map.sname');
            $snameVar = config('shibboleth.attribute_map.gname');
            $mailVar = config('shibboleth.attribute_map.email');
            $employeetypeVar = config('shibboleth.attribute_map.employeetype');
            // Check if the required attributes are present in the $_SERVER array
            if (isset($_SERVER[$snameVar], $_SERVER[$gnameVar], $_SERVER[$mailVar], $_SERVER[$employeetypeVar])) {
                // Return user information
                return $userInfo = [
                    'username' => $_SERVER['REMOTE_USER'],
                    'name' => $_SERVER[$gnameVar].' '.$_SERVER[$snameVar],
                    'email' => $_SERVER[$mailVar],
                    'employeetype' => $_SERVER[$employeetypeVar]
                ];
            } else {
                // Error handling if attributes are missing
                return response()->json(['error' => 'Missing required attributes'], 400);
            }
        } else {
            // Redirect to the Shibboleth login page
            $loginPath = config('shibboleth.login_path');
            if (!empty($loginPath)) {
                redirect($loginPath)->send();
            } else {
                // Error handling if the login path is not set
                return response()->json(['error' => 'Login path is not set'], 500);
            }
        }
    }
}
