<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection;


use App\Models\ExtApp;
use App\Models\User;
use App\Services\ExtApp\ConnectRequestCrypto as ExtAppConnectRequestCrypto;
use App\Services\ExtApp\Repositories\ExtAppRepository;
use App\Services\ExtApp\Repositories\ExtAppUserRepository;
use App\Services\Frontend\Connection\Values\Connection;
use App\Services\Frontend\Connection\Values\ConnectionType;
use App\Services\Frontend\Connection\Values\ExtAppConnectRequestPayload;
use App\Services\Frontend\Connection\Values\ExtAppSecrets;
use App\Services\Frontend\Connection\Values\Userinfo;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\UserTypes\Values\RegisteringUser;
use App\Services\Translation\LocaleService;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

#[Singleton]
readonly class ConnectionFactory
{
    public function __construct(
        #[Config('app.version')]
        private string                      $version,
        private ExtAppConnectRequestCrypto  $connectRequestCrypto,
        private ExtAppRepository            $extAppRepository,
        private ExtAppUserRepository        $extAppUserRepository,
        private Request                     $request,
        private FrontendMigrationRepository $migrationRepository,
        private LoggerInterface             $logger,
        private LocaleService               $localeService
    )
    {
    }

    public function createHawkiConnection(): Connection|null
    {
        $userContext = $this->request->getUserContext();
        if (!$this->request->getUsageContext()->isMainApp()) {
            $this->logger->warning('Attempt to access hawki connection from non-main app', [
                'usageContext' => $this->request->getUsageContext()->get(),
                'userContext' => $userContext->get(),
            ]);
            return null;
        }

        $type = (static function () use ($userContext) {
            if ($userContext->isUser()) {
                return ConnectionType::INTERNAL_AUTHENTICATED;
            }
            if ($userContext->isRegisteringUser()) {
                return ConnectionType::INTERNAL_REGISTERING_USER;
            }
            return ConnectionType::INTERNAL;
        })();

        $userinfo = match ($type) {
            ConnectionType::INTERNAL_AUTHENTICATED => $this->makeUserinfo($this->request->user()),
            ConnectionType::INTERNAL_REGISTERING_USER => $this->makeUserinfo($userContext->getRegisteringUser()),
            default => null
        };

        return new Connection(
            id: 'hawki',
            type: $type,
            version: $this->version,
            locale: $this->localeService->getCurrentLocale(),
            userinfo: $userinfo,
            migrationsToApply: $this->countMigrationsToApply()
        );
    }

    public function createExtAppConnection(string $extAppUserId): Connection|null
    {
        $app = $this->tryFindingExtApp();
        if (!$app) {
            return null;
        }

        $appUser = $this->extAppUserRepository->findByExternalId($app, $extAppUserId);
        if (!$appUser) {
            $this->logger->debug(sprintf(
                'No app user %s found for app %s',
                $extAppUserId,
                $app->id
            ));

            return $this->createExtAppConnectConnection($app, $extAppUserId);
        }

        $userinfo = $this->makeUserinfo($appUser->user);

        return new Connection(
            id: $extAppUserId,
            type: ConnectionType::EXTERNAL_APP_AUTHENTICATED,
            version: $this->version,
            locale: $this->localeService->getCurrentLocale(),
            userinfo: $userinfo,
            extAppSecrets: new ExtAppSecrets(
                passkey: (string)$appUser->passkey,
                apiToken: (string)$appUser->api_token,
                privateKey: (string)$appUser->user_private_key,
            )
        );
    }

    private function createExtAppConnectConnection(ExtApp $app, string $extAppUserId): Connection
    {
        $payload = ExtAppConnectRequestPayload::fromArray([
            'appId' => $app->id,
            'version' => $this->version,
            'extAppUserId' => $extAppUserId,
        ]);

        return new Connection(
            id: $extAppUserId,
            type: ConnectionType::EXTERNAL_APP,
            version: $this->version,
            locale: $this->localeService->getCurrentLocale(),
            extAppConnectRequest: $this->connectRequestCrypto->encryptPayload($payload, $app)
        );
    }

    private function tryFindingExtApp(): ExtApp|null
    {
        // Only when coming from an external app, we want to find ext app connections
        if (!$this->request->getUsageContext()->isExternalApp()) {
            $this->logger->warning('Attempt to access external app connection from non-external app', [
                'usageContext' => $this->request->getUsageContext()->get(),
                'userContext' => $this->request->getUserContext()->get(),
            ]);
            return null;
        }

        // Only the app itself (not one of its users) can access the connection resource for an external app.
        if (!$this->request->getUserContext()->isExternalApp()) {
            $this->logger->warning('Attempt to access external app connection with non-external app user context', [
                'usageContext' => $this->request->getUsageContext()->get(),
                'userContext' => $this->request->getUserContext()->get(),
            ]);
            return null;
        }

        $requestUser = $this->request->user();
        $app = $this->extAppRepository->findOneByUser($requestUser);
        if (!$app) {
            $this->logger->warning('Attempt to access external app connection with user that is not associated with an app', [
                'usageContext' => $this->request->getUsageContext()->get(),
                'userContext' => $this->request->getUserContext()->get(),
                'requestUserId' => $requestUser->id,
            ]);
            return null;
        }

        return $app;
    }

    private function makeUserinfo(User|RegisteringUser $user): Userinfo
    {
        if ($user instanceof RegisteringUser) {
            return new Userinfo(
                id: 0,
                name: $user->name,
                username: $user->username,
                email: $user->email,
                hash: '',
                avatar: null
            );
        }

        return new Userinfo(
            id: $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            hash: md5($user->id . '-' . $user->publicKey),
            avatar: StoredFileIdentifier::tryFromUserAvatar($user),
            bio: $user->bio
        );
    }

    private function countMigrationsToApply(): int
    {
        if (!$this->request->user()) {
            return 0;
        }
        return $this->migrationRepository->findAllMigrationsToApplyForUser($this->request->user())->count();
    }
}
