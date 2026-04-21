<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use App\Models\ExtAppUserRequest;
use App\Services\ExtApp\Db\AppUserRequestDb;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Hawk\HawkiCrypto\HybridCrypto;

readonly class AppUserRequestCreator
{
    public function __construct(
        protected HybridCrypto     $hybridCrypto,
        protected AsymmetricCrypto $asymmetricCrypto,
        protected AppUserRequestDb $appUserRequestDb
    )
    {
    }
    
    /**
     * Creates a new ExtAppUserRequest for the given app and external user ID.
     *
     * @param ExtApp $app The app for which the request is created.
     * @param string $extUserId The external user ID.
     * @return ExtAppUserRequest The created ExtAppUserRequest instance.
     */
    public function create(ExtApp $app, string $extUserId): ExtAppUserRequest
    {
        $keypair = $this->asymmetricCrypto->generateKeyPair();
        
        return $this->appUserRequestDb->create(
            appId: $app->id,
            extUserId: $extUserId,
            publicKey: $keypair->publicKey,
            privateKey: $this->hybridCrypto->encrypt(
                (string)$keypair->privateKey,
                $app->app_public_key
            ),
            requestId: bin2hex(random_bytes(32)) . hash('sha256', $extUserId . '-' . $app->id),
            validUntil: now()->addSeconds((int)config('external_access.app_connect_request_timeout', 60 * 15))
        );
    }
}
