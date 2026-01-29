<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection;


use App\Models\ExtAppUser;
use App\Models\ExtAppUserRequest;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Chat\Attachment\AttachmentService;
use App\Services\Encryption\SaltProvider;
use App\Services\ExtApp\ExtAppFeatureSwitch;
use App\Services\Frontend\Connection\Value\AiConfig;
use App\Services\Frontend\Connection\Value\Builder\RouteConfigBuilder;
use App\Services\Frontend\Connection\Value\Connection\ExtAppConnection;
use App\Services\Frontend\Connection\Value\Connection\ExtAppRequestConnection;
use App\Services\Frontend\Connection\Value\Connection\InternalConnection;
use App\Services\Frontend\Connection\Value\ExtAppSecrets;
use App\Services\Frontend\Connection\Value\FeatureFlags;
use App\Services\Frontend\Connection\Value\InternalSecrets;
use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Services\Frontend\Connection\Value\RouteConfig;
use App\Services\Frontend\Connection\Value\Salts;
use App\Services\Frontend\Connection\Value\StorageConfig;
use App\Services\Frontend\Connection\Value\TransferConfig;
use App\Services\Frontend\Connection\Value\Userinfo;
use App\Services\Frontend\Connection\Value\WebsocketConfig;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Translation\LocaleService;
use App\Services\User\Keychain\UserKeychainDb;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\URL;
use Route;

readonly class ConnectionFactory
{
    public function __construct(
        private SaltProvider         $saltProvider,
        private UserKeychainDb       $keychainDb,
        private Repository           $config,
        private AiService            $aiService,
        private AttachmentService    $attachmentService,
        private AvatarStorageService $avatarStorageService,
        private FileStorageService   $fileStorageService,
        private LocaleService        $localeService,
        private ExtAppFeatureSwitch  $featureSwitch
    )
    {
    }
    
    public function createExtAppConnection(ExtAppUser $extAppUser): ExtAppConnection
    {
        return new ExtAppConnection(
            version: $this->config->get('app.version'),
            locale: $this->createLocaleConfig(),
            featureFlags: new FeatureFlags(
                aiInGroups: $this->featureSwitch->isAiInGroupsEnabled()
            ),
            ai: $this->createAiConfig(true),
            userinfo: $this->createUserinfo($extAppUser),
            salts: $this->createSalts(),
            storage: $this->createStorageConfig(),
            transfer: $this->createTransferConfig(
                $this->createExtAppRouteConfig()
            ),
            secrets: $this->createExtAppSecrets($extAppUser),
        );
    }
    
    public function createExtAppRequestConnection(ExtAppUserRequest $request): ExtAppRequestConnection
    {
        URL::macro('getForcedRoot', fn() => $this->forcedRoot);
        $forcedRoot = URL::getForcedRoot();
        try {
            URL::useOrigin($this->config->get('app.url'));
            return new ExtAppRequestConnection(
                version: $this->config->get('app.version'),
                locale: $this->createLocaleConfig(),
                connectUrl: route('web.apps.connect', ['request_id' => $request->request_id])
            );
        } finally {
            URL::useOrigin($forcedRoot);
        }
    }
    
    public function createInternalConnection(User $user): InternalConnection
    {
        return new InternalConnection(
            version: $this->config->get('app.version'),
            locale: $this->createLocaleConfig(),
            featureFlags: FeatureFlags::createAllowAll(),
            ai: $this->createAiConfig(false),
            userinfo: $this->createUserinfo($user),
            salts: $this->createSalts(),
            storage: $this->createStorageConfig(),
            transfer: $this->createTransferConfig(
                $this->createInternalRouteConfig()
            ),
            secrets: $this->createInternalSecrets(),
        );
    }
    
    private function createSalts(): Salts
    {
        return new Salts(
            userdata: $this->saltProvider->getSaltForUserDataEncryption(),
            invitation: $this->saltProvider->getSaltForInvitation(),
            ai: $this->saltProvider->getSaltForAiCrypto(),
            passkey: $this->saltProvider->getSaltForPasskey(),
            backup: $this->saltProvider->getSaltForBackup(),
        );
    }
    
    private function createUserinfo(User|ExtAppUser $user): Userinfo
    {
        if ($user instanceof ExtAppUser) {
            $user = $user->user;
        }
        
        return new Userinfo(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            hash: md5($user->id . '-' . $user->publicKey)
        );
    }
    
    private function createTransferConfig(RouteConfig $routes): TransferConfig
    {
        return new TransferConfig(
            baseUrl: $this->config->get('app.url'),
            websocket: new WebsocketConfig(
                key: $this->config->get('reverb.frontend.key'),
                host: $this->config->get('reverb.frontend.host'),
                port: $this->config->get('reverb.frontend.port'),
                forceTLS: $this->config->get('reverb.frontend.scheme', 'https') === 'https',
            ),
            routes: $routes
        );
    }
    
    private function createStorageConfig(): StorageConfig
    {
        return new StorageConfig(
            maxFileSize: $this->attachmentService->getMaxFileSize(),
            maxAvatarFileSize: $this->avatarStorageService->getMaxFileSize(),
            allowedMimeTypes: $this->fileStorageService->getAllowedMimeTypes(),
            allowedAvatarMimeTypes: $this->avatarStorageService->getAllowedMimeTypes(),
            maxAttachmentFiles: $this->attachmentService->getMaxAttachments()
        );
    }
    
    private function createAiConfig(bool $externalApp): AiConfig
    {
        return new AiConfig(
            handle: $this->aiService->getAiHandle(),
            models: $this->aiService->getAvailableModels($externalApp),
        );
    }
    
    private function createExtAppSecrets(ExtAppUser $user): ExtAppSecrets
    {
        return new ExtAppSecrets(
            passkey: (string)$user->passkey,
            apiToken: (string)$user->api_token,
            privateKey: (string)$user->user_private_key,
        );
    }
    
    private function createInternalSecrets(): InternalSecrets
    {
        return new InternalSecrets(
            csrfToken: csrf_token(),
        );
    }
    
    private function createRouteConfigBuilder(): RouteConfigBuilder
    {
        $builder = new RouteConfigBuilder(Route::getRoutes());
        
        $builder
            ->addRoute('syncLog', 'web.syncLog', 'api.external_app.syncLog')
            ->addRoute('keychainPasskeyValidator', 'web.keychainPasskeyValidator', 'api.external_app.keychainPasskeyValidator')
            ->addRoute('keychainUpdate', 'web.keychainUpdate', 'api.external_app.keychainUpdate')
            ->addRoute('profileUpdate', 'web.profileUpdate', 'api.external_app.profileUpdate')
            ->addRoute('profileAvatarUpload', 'web.profileAvatarUpload', 'api.external_app.profileAvatarUpload')
            ->addRoute('storageProxy', 'web.storage.proxy', 'api.external_app.storage.proxy')
            ->addRoute('roomCreate', 'web.roomCreate', 'api.external_app.roomCreate')
            ->addRoute('roomUpdate', 'web.roomUpdate', 'api.external_app.roomUpdate')
            ->addRoute('roomRemove', 'web.roomRemove', 'api.external_app.roomRemove')
            ->addRoute('roomMemberCandidateSearch', 'web.roomMemberCandidateSearch', 'api.external_app.roomMemberCandidateSearch')
            ->addRoute('roomInviteMember', 'web.roomInviteMember', 'api.external_app.roomInviteMember')
            ->addRoute('roomEditMember', 'web.roomEditMember', 'api.external_app.roomEditMember')
            ->addRoute('roomRemoveMember', 'web.roomRemoveMember', 'api.external_app.roomRemoveMember')
            ->addRoute('roomLeave', 'web.roomLeave', 'api.external_app.roomLeave')
            ->addRoute('roomInvitationAccept', 'web.roomInvitationAccept', 'api.external_app.roomInvitationAccept')
            ->addRoute('roomAvatarUpload', 'web.roomAvatarUpload', 'api.external_app.roomAvatarUpload')
            ->addRoute('roomMessagesMarkRead', 'web.roomMessagesMarkRead', 'api.external_app.roomMessagesMarkRead')
            ->addRoute('roomMessagesSend', 'web.roomMessagesSend', 'api.external_app.roomMessagesSend')
            ->addRoute('roomMessagesEdit', 'web.roomMessagesEdit', 'api.external_app.roomMessagesEdit')
            ->addRoute('roomMessagesAiSend', 'web.roomMessagesAiSend', 'api.external_app.roomMessagesAiSend')
            ->addRoute('roomMessagesAttachmentUpload', 'web.roomMessagesAttachmentUpload', 'api.external_app.roomMessagesAttachmentUpload');
        
        return $builder;
    }
    
    private function createExtAppRouteConfig(): RouteConfig
    {
        return $this->createRouteConfigBuilder()->buildExtAppRouteConfig();
    }
    
    private function createInternalRouteConfig(): RouteConfig
    {
        return $this->createRouteConfigBuilder()->buildInternalRouteConfig();
    }
    
    private function createLocaleConfig(): LocaleConfig
    {
        return new LocaleConfig(
            preferred: $this->localeService->getCurrentLocale(),
            default: $this->localeService->getDefaultLocale(),
            available: $this->localeService->getAvailableLocales()
        );
    }
}
