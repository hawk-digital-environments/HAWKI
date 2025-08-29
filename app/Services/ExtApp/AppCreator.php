<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Events\AppCreateEvent;
use App\Models\User;
use App\Services\ExtApp\Db\AppDb;
use App\Services\ExtApp\Db\UserDb;
use App\Services\ExtApp\Value\AppCreationResult;
use DB;
use Hawk\HawkiCrypto\AsymmetricCrypto;
use Throwable;

class AppCreator
{
    public function __construct(
        protected AsymmetricCrypto $crypto,
        protected UserDb           $userDb,
        protected AppDb            $appDb
    )
    {
    }
    
    /**
     * Creates a new external application with a user account, keypair, and app record.
     * Please note, that the private rsa key is not stored in the database!
     *
     * @param string $name A human-readable name for the app, used for display purposes.
     * @param string $redirectUrl The url of the external application to which the user will be redirected after connecting their accounts.
     * @param string|null $url An optional url to the app, used for display purposes. This is the URL where the app is hosted.
     * @param string|null $description An optional description of the app, used for display purposes.
     * @param string|null $logoUrl An optional url to the logo of the app, used for display purposes.
     * @throws Throwable
     */
    public function create(
        string  $name,
        string  $redirectUrl,
        ?string $url,
        ?string $description,
        ?string $logoUrl
    ): AppCreationResult
    {
        $result = DB::transaction(function () use ($name, $url, $redirectUrl, $description, $logoUrl) {
            $user = $this->createAppUser($name);
            $token = $this->userDb->createAppApiUserToken($user);
            $keypair = $this->crypto->generateKeypair();
            $app = $this->appDb->createApp(
                user: $user,
                keypair: $keypair,
                name: $name,
                url: $url,
                redirectUrl: $redirectUrl,
                description: $description,
                logoUrl: $logoUrl
            );
            
            return new AppCreationResult(
                app: $app,
                appUser: $user,
                keypair: $keypair,
                token: $token
            );
        });
        
        AppCreateEvent::dispatch($result->app);
        
        return $result;
    }
    
    protected function createAppUser(string $appName): User
    {
        $emailReadyName = preg_replace('/[^a-z0-9\-_.]+/i', '-', strtolower($appName));
        $email = $emailReadyName . '@app.hawki.org';
        
        return $this->userDb->createAppApiUser(
            name: $appName,
            email: $email,
            username: 'APP: ' . $emailReadyName,
        );
    }
}
