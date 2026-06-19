<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Repositories;


use App\Events\PersonalAccessTokenCreatedEvent;
use App\Http\Middleware\SystemContextBootingMiddleware;
use App\Models\ExtApp;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Laravel\Sanctum\NewAccessToken;

#[UseModel(User::class)]
class UserRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Creates a new user specifically for app API access. This user will NOT be able to log in via the normal login methods.
     * @param string $name The visible name of the user (Currently not used anywhere; but might be useful for future features)
     * @param string $email The email of the user (Currently not used anywhere; but might be useful for future features)
     * @param string $username The unique username of the user (Currently not used anywhere; but might be useful for future features)
     * @return User The created user.
     */
    public function createUserToActAsExternalApp(
        string $name,
        string $email,
        string $username,
    ): User
    {
        return $this->getQueryWithoutContextualScopes()->create([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'publicKey' => '',
            'password' => bcrypt(bin2hex(random_bytes(16))),
            'employeetype' => ExtApp::EMPLOYEE_TYPE_APP,
            'isRemoved' => false,
        ]);
    }

    /**
     * Creates a new API token for the given user with the appropriate scope for app access.
     * @param User $user The user to create the token for. This user should be created via createAppApiUser().
     */
    public function createAppApiUserToken(User $user): NewAccessToken
    {
        $token = $user->createToken(ExtApp::APP_TOKEN_NAME, [SystemContextBootingMiddleware::EXT_APP_TOKEN_SCOPE]);
        PersonalAccessTokenCreatedEvent::dispatch($user, $token);
        return $token;
    }

    /**
     * Creates a new API token for the given user with the appropriate scope for app access.
     * This method allows specifying a custom token name.
     * @param string $tokenName The name of the token to be created.
     * @param User $user The user to create the token for. This MUST NOT be the apps api user, but a user to access their data.
     */
    public function createTokenForUserOfApp(string $tokenName, User $user): NewAccessToken
    {
        $token = $user->createToken($tokenName);
        PersonalAccessTokenCreatedEvent::dispatch($user, $token);
        return $token;
    }
}
