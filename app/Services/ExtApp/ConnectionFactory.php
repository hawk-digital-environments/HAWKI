<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Http\Resources\ExtAppConnectionResource;
use App\Models\ExtApp;
use App\Models\ExtAppUser;
use App\Services\AI\AIConnectionService;
use App\Services\SaltProvider;
use App\Services\User\UserKeychainDb;
use Illuminate\Config\Repository;

readonly class ConnectionFactory
{
    public function __construct(
        protected SaltProvider        $saltProvider,
        protected UserKeychainDb      $keychainDb,
        protected Repository          $config,
        protected AIConnectionService $aiConnectionService
    )
    {
    
    }
    
    public function create(
        ExtApp     $app,
        ExtAppUser $user
    ): ExtAppConnectionResource
    {
        return new ExtAppConnectionResource(
            websocket: $this->config->get('reverb.frontend'),
            baseUrl: $this->config->get('app.url'),
            aiHandle: $this->config->get('app.aiHandle'),
            aiModels: $this->aiConnectionService->getAvailableModels(true),
            salt: [
                'userdata' => $this->saltProvider->getSaltForUserDataEncryption(),
                'passkey' => $this->saltProvider->getSaltForPasskey(),
                'ai' => $this->saltProvider->getSaltForAiCrypto(),
            ],
            userinfo: [
                'id' => $user->user->id,
                'username' => $user->user->username,
                'email' => $user->user->email,
            ],
            userSecrets: [
                'user_public_key' => (string)$user->user_public_key,
                'api_token' => (string)$user->api_token,
                'keychain' => $this->keychainDb->findByUser($user->user),
            ],
        );
    }
}
