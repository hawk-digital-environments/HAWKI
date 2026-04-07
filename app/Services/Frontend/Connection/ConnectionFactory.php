<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection;


use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Encryption\SaltProvider;
use App\Services\Frontend\Connection\Value\AiConfig;
use App\Services\Frontend\Connection\Value\Builder\RouteConfigBuilder;
use App\Services\Frontend\Connection\Value\Connection\InternalConnection;
use App\Services\Frontend\Connection\Value\Connection\InternalLoginConnection;
use App\Services\Frontend\Connection\Value\FeatureFlags;
use App\Services\Frontend\Connection\Value\InternalSecrets;
use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Services\Frontend\Connection\Value\RouteConfig;
use App\Services\Frontend\Connection\Value\Salts;
use App\Services\Frontend\Connection\Value\StorageConfig;
use App\Services\Frontend\Connection\Value\TransferConfig;
use App\Services\Frontend\Connection\Value\TranslatorConfig;
use App\Services\Frontend\Connection\Value\Userinfo;
use App\Services\Frontend\Connection\Value\WebsocketConfig;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Translation\LocaleService;
use Illuminate\Config\Repository;
use Illuminate\Translation\Translator;
use Route;
use Symfony\Component\Mime\MimeTypes;

readonly class ConnectionFactory
{
    public function __construct(
        private SaltProvider         $saltProvider,
        private Repository           $config,
        private AiService            $aiService,
        private AvatarStorageService $avatarStorageService,
        private FileStorageService   $fileStorageService,
        private LocaleService $localeService,
        private Translator    $translator
    )
    {
    }

    public function createInternalLoginConnection(): InternalLoginConnection
    {
        return new InternalLoginConnection(
            version: $this->config->get('app.version'),
            locale: $this->createLocaleConfig(),
            translation: $this->createInternalTranslatorConfig()
        );
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
            translation: $this->createInternalTranslatorConfig()
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

    private function createUserinfo(User $user): Userinfo
    {
        return new Userinfo(
            id: $user->id,
            username: $user->username,
            email: $user->email,
            hash: md5($user->id . '-' . $user->publicKey)
        );
    }

    private function createTransferConfig(RouteConfig $routes): TransferConfig
    {
        //@todo: $appUrlScheme === 'https' ? 443 : 80) overrides the whole reverb env variable -> it also kills my local dev
        $appUrl = $this->config->get('app.url');
        $appUrlScheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
        $appUrlHost = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $appUrlPath = parse_url($appUrl, PHP_URL_PATH);
        $appUrlPort = parse_url($appUrl, PHP_URL_PORT) ?? ($appUrlScheme === 'https' ? 443 : 8080);
        return new TransferConfig(
            baseUrl: $appUrl,
            websocket: new WebsocketConfig(
                key: $this->config->get('reverb.frontend.key'),
                host: $appUrlHost,
                port: $appUrlPort,
                forceTLS: $this->config->get('reverb.frontend.scheme', 'https') === 'https',
                path: $appUrlPath,
            ),
            routes: $routes
        );
    }

    private function createStorageConfig(): StorageConfig
    {
        $extensionsFromMimeTypes = static function (array $mimeTypes) {
            $mime = new MimeTypes();
            $extensions = [];
            foreach ($mimeTypes as $mimeType) {
                $extensions[] = $mime->getExtensions($mimeType);
            }
            return array_values(
                array_filter(
                    array_unique(array_merge(...$extensions)),
                    static function ($ext) {
                        // Some extensions are weird like "[1-9]", we want to filter those out, basically contain only characters and numbers
                        return preg_match('/^[a-zA-Z0-9+]+$/', $ext);
                    }
                )
            );
        };

        $allowedMimeTypes = array_values(array_unique($this->fileStorageService->getAllowedMimeTypes()));
        $allowedAvatarMimeTypes = array_values(array_unique($this->avatarStorageService->getAllowedMimeTypes()));
        return new StorageConfig(
            maxFileSize: $this->fileStorageService->getMaxFileSize(),
            maxAvatarFileSize: $this->avatarStorageService->getMaxFileSize(),
            allowedMimeTypes: $allowedMimeTypes,
            allowedExtensions: $extensionsFromMimeTypes($allowedMimeTypes),
            allowedAvatarMimeTypes: $allowedAvatarMimeTypes,
            allowedAvatarExtensions: $extensionsFromMimeTypes($allowedAvatarMimeTypes),
            maxAttachmentFiles: $this->config->get('filesystems.upload_limits.max_attachment_files')
        );
    }

    private function createAiConfig(bool $externalApp): AiConfig
    {
        return new AiConfig(
            handle: $this->aiService->getAiHandle(),
            models: $this->aiService->getAvailableModels($externalApp),
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

        // @todo this will be added in a future version

        return $builder;
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

    private function createInternalTranslatorConfig(): TranslatorConfig
    {
        return new TranslatorConfig(
            labels: $this->translator->get('*', locale: $this->localeService->getCurrentLocale()->lang)
        );
    }
}
