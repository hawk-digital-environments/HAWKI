<?php

namespace App\Services\Auth;

use Jumbojett\OpenIDConnectClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;

class OidcService
{
    protected $oidc;

    public function __construct()
    {
        $env = $this->getEnvConfig();
        $this->oidc = new OpenIDConnectClient(
            $env['OIDC_IDP'],
            $env['OIDC_CLIENT_ID'],
            $env['OIDC_CLIENT_SECRET']
        );

        if (!empty($env['TESTUSER'])) {
            $this->oidc->setHttpUpgradeInsecureRequests(false);
        }

        // Add scopes as an array
        $this->oidc->addScope(['profile', 'email']);
    }

    protected function getEnvConfig()
    {
        if (file_exists(base_path('.env'))) {
            return [
                'OIDC_IDP' => env('OIDC_IDP'),
                'OIDC_CLIENT_ID' => env('OIDC_CLIENT_ID'),
                'OIDC_CLIENT_SECRET' => env('OIDC_CLIENT_SECRET'),
                'TESTUSER' => env('TESTUSER'),
            ];
        } else {
            throw new Exception('.env file not found');
        }
    }

    public function authenticate()
    {
        $this->oidc->authenticate();

        // Retrieve user information
        $email = $this->oidc->requestUserInfo('email');
        $firstname = $this->oidc->requestUserInfo('given_name');
        $surname = $this->oidc->requestUserInfo('family_name');
        $name = $firstname . ' ' . $surname;

        // Find or create the user in the local database
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'username' => $email, 'password' => Hash::make(str_random(16))]
        );

        // Log the user in using Laravel's Auth facade
        Auth::login($user);

        return $user;
    }
}
