<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use App\Models\User;
use App\Services\ExtApp\Db\AppDb;
use App\Services\ExtApp\Exception\FailedToResolveAppByIdException;
use App\Services\ExtApp\Value\AppUserRequestSessionValue;

readonly class AppUserRequestActionHandler
{
    public function __construct(
        protected AppUserRequestSessionStorage $sessionStorage,
        protected AppUserCreator               $appUserCreator,
        protected AppDb                        $appDb,
        protected AppUserRedirectBuilder       $redirectBuilder
    )
    {
    }
    
    /**
     * Handles the decline of a user request.
     * @param AppUserRequestSessionValue $userRequest The session value containing the details of the user request.
     * @return string Returns the redirect URL to send the user to after declining the request.
     */
    public function decline(AppUserRequestSessionValue $userRequest): string
    {
        $app = $this->resolveAppOrFail($userRequest);
        $this->sessionStorage->clear();
        return $this->redirectBuilder->decline($app);
    }
    
    /**
     * Handles the conversion of a user request into an actual AppUser.
     * @param string $passkey The passkey to be used for the AppUser. This value is encrypted by the users public key.
     * @param User $user The user who is accepting the request (and will be linked to the AppUser).
     * @param AppUserRequestSessionValue $userRequest The session value containing the details of the user request.
     * @return string Returns the redirect URL to send the user to after accepting the request.
     */
    public function accept(string $passkey, User $user, AppUserRequestSessionValue $userRequest): string
    {
        $app = $this->resolveAppOrFail($userRequest);
        $this->appUserCreator->create(
            $user,
            $app,
            $passkey,
            $userRequest->userPublicKey,
            $userRequest->userPrivateKey,
            $userRequest->extUserId
        );
        $this->sessionStorage->clear();
        return $this->redirectBuilder->accept($app);
    }
    
    protected function resolveAppOrFail(AppUserRequestSessionValue $userRequest): ExtApp
    {
        $app = $this->appDb->findById($userRequest->appId);
        if (!$app) {
            throw new FailedToResolveAppByIdException($userRequest->appId);
        }
        
        return $app;
    }
}
