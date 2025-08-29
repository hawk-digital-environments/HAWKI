<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Events\AppUserCreateEvent;
use App\Models\ExtApp;
use App\Models\ExtAppUser;
use App\Models\User;
use App\Services\ExtApp\Db\AppUserDb;
use App\Services\ExtApp\Db\UserDb;
use Hawk\HawkiCrypto\HybridCrypto;
use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;

class AppUserCreator
{
    public function __construct(
        protected HybridCrypto $hybridCrypto,
        protected UserDb       $userDb,
        protected AppUserDb    $appUserDb,
    )
    {
    }
    
    /**
     * Creates a new entry in the app_users table.
     * An app_user represents the mapping of an external user to a hawki user for a specific app.
     * @param User $hawkiUser The hawki user to link to
     * @param ExtApp $app The app for which the user is being created
     * @param string $passkey The passkey to be used for the AppUser. This value is encrypted by the users public key.
     * @param AsymmetricPublicKey $publicKey The public key of the user. This is used to encrypt all data to be sent to the user.
     * @param HybridCryptoValue $privateKey The private key of the user. This is NOT clear text, but encrypted by the apps public key, so only the app can decrypt it.
     * @param string $extUserId The external user id, as known by the app.
     */
    public function create(
        User                $hawkiUser,
        ExtApp              $app,
        string              $passkey,
        AsymmetricPublicKey $publicKey,
        HybridCryptoValue   $privateKey,
        string              $extUserId,
    ): ExtAppUser
    {
        $apiToken = $this->userDb->createTokenForUserOfApp(
            ExtApp::APP_USER_TOKEN_NAME_PREFIX . ' ' . $app->name . '(' . $app->id . ')',
            $hawkiUser
        );
        
        $user = $this->appUserDb->create(
            appId: $app->id,
            userId: $hawkiUser->id,
            passkey: $passkey,
            publicKey: $publicKey,
            privateKey: $privateKey,
            extUserId: $extUserId,
            apiTokenId: $apiToken->accessToken->id,
            apiToken: $this->hybridCrypto->encrypt(
                $apiToken->plainTextToken,
                $app->app_public_key
            )
        );
        
        AppUserCreateEvent::dispatch($user);
        
        return $user;
    }
}
