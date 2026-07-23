<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use App\Models\ExtAppUser;
use App\Models\User;
use App\Services\ExtApp\Repositories\ExtAppRepository;
use App\Services\ExtApp\Repositories\ExtAppUserRepository;
use App\Services\ExtApp\Repositories\UserRepository;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Hawk\HawkiCrypto\HybridCrypto;

readonly class ExtAppUserConnector
{
    public function __construct(
        private HybridCrypto         $hybridCrypto,
        private UserRepository       $userRepository,
        private AsymmetricCrypto     $asymmetricCrypto,
        private ExtAppRepository     $extAppRepository,
        private ExtAppUserRepository $extAppUserRepository,
        private ConnectRequestCrypto $connectRequestCrypto
    )
    {
    }

    public function connect(
        User   $hawkiUser,
        string $passkey,
        string $connectRequestString
    ): ExtAppUser|null
    {
        $payload = $this->connectRequestCrypto->decryptPayload($connectRequestString);
        if (!$payload) {
            return null;
        }

        $app = $this->extAppRepository->findOne($payload->appId);
        if (!$app) {
            return null;
        }

        // Check if user is already connected, if so, return the existing connection instead of creating a new one
        $existingAppUser = $this->extAppUserRepository->findByExternalId($app, $payload->extAppUserId);
        if ($existingAppUser) {
            return $existingAppUser;
        }

        $keypair = $this->asymmetricCrypto->generateKeyPair();

        $apiToken = $this->userRepository->createTokenForUserOfApp(
            ExtApp::APP_USER_TOKEN_NAME_PREFIX . ' ' . $app->name . '(' . $app->id . ')',
            $hawkiUser
        );

        return $this->extAppUserRepository->create(
            appId: $app->id,
            userId: $hawkiUser->id,
            passkey: $passkey,
            publicKey: $keypair->publicKey,
            privateKey: $this->hybridCrypto->encrypt(
                (string)$keypair->privateKey,
                $app->app_public_key
            ),
            extUserId: $payload->extAppUserId,
            apiTokenId: $apiToken->accessToken->id,
            apiToken: $this->hybridCrypto->encrypt(
                $apiToken->plainTextToken,
                $app->app_public_key
            )
        );
    }
}
