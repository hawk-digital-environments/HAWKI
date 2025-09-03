<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Http\Resources\ExtAppConnectionResource;
use App\Models\ExtAppUser;
use App\Services\AI\AiService;
use App\Services\SaltProvider;
use App\Services\User\UserKeychainDb;
use Illuminate\Config\Repository;

readonly class ConnectionFactory
{
    public function __construct(
        private SaltProvider   $saltProvider,
        private UserKeychainDb $keychainDb,
        private Repository     $config,
        private AiService      $aiService,
    )
    {
    }
    
    public function create(
        ExtAppUser $user
    ): ExtAppConnectionResource
    {
        return new ExtAppConnectionResource(
            websocket: $this->config->get('reverb.frontend'),
            baseUrl: $this->config->get('app.url'),
            aiHandle: $this->config->get('app.aiHandle'),
            aiModels: $this->aiService->getAvailableModels(true)->toArray(),
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
                'passkey' => $user->passkey,
                'api_token' => $user->api_token,
                'private_key' => $user->user_private_key,
                'keychain' => $this->keychainDb->findByUser($user->user),
            ],
        );
    }
}
