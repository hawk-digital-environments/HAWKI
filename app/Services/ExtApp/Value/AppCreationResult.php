<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Value;


use App\Models\ExtApp;
use App\Models\User;
use Hawk\HawkiCrypto\Value\AsymmetricKeypair;
use Laravel\Sanctum\NewAccessToken;

readonly class AppCreationResult
{
    public function __construct(
        public ExtApp            $app,
        public User              $appUser,
        public AsymmetricKeypair $keypair,
        public NewAccessToken    $token,
    )
    {
    
    }
}
