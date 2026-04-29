<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\CreateSanctumTokenForUser;
use App\Models\User;
use App\Services\Profile\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(CreateSanctumTokenForUser::class)]
class CreateSanctumTokenForUserTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'publicKey' => str_repeat('x', 32),
            'employeetype' => 'staff',
            'isRemoved' => false,
        ], $overrides));
    }

    private function bindTokenServiceForUser(?User $user): ApiTokenService
    {
        $service = new ApiTokenService($user, $this->app->make(LoggerInterface::class));
        $this->app->instance(ApiTokenService::class, $service);
        return $service;
    }

    public function test_create_token_by_username(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsQuestion('Enter a name for the token (max 16 characters)', 'test-token')
            ->expectsOutput('Token created successfully:')
            ->expectsOutput('Token Name: test-token')
            ->expectsOutput('IMPORTANT: Copy this token now - it will not be shown again!')
            ->assertExitCode(0);
    }

    public function test_create_token_by_email(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Email Address', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Email Address', 'test@example.com')
            ->expectsQuestion('Enter a name for the token (max 16 characters)', 'email-token')
            ->expectsOutput('Token created successfully:')
            ->expectsOutput('Token Name: email-token')
            ->assertExitCode(0);
    }

    public function test_create_token_by_userid(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'UserID', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the UserID', (string) $user->id)
            ->expectsQuestion('Enter a name for the token (max 16 characters)', 'id-token')
            ->expectsOutput('Token created successfully:')
            ->expectsOutput('Token Name: id-token')
            ->assertExitCode(0);
    }

    public function test_user_not_found(): void
    {
        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'nonexistent')
            ->expectsOutput('User not found!')
            ->assertExitCode(0);
    }

    public function test_suspended_user(): void
    {
        $this->createUser(['isRemoved' => true]);

        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('User account is suspended!')
            ->assertExitCode(0);
    }

    public function test_revoke_token(): void
    {
        $user = $this->createUser();
        $user->createToken('token-to-revoke');
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token', ['--revoke' => true])
            ->expectsOutput('You are about to revoke an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('Available tokens:')
            ->expectsQuestion('Enter the token ID to revoke', '1')
            ->expectsOutput('Token successfully revoked.')
            ->assertExitCode(0);
    }

    public function test_revoke_token_with_no_tokens(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token', ['--revoke' => true])
            ->expectsOutput('You are about to revoke an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('No tokens found for this user.')
            ->expectsQuestion('Enter the token ID to revoke', '99')
            ->expectsOutput('Token successfully revoked.')
            ->assertExitCode(0);
    }

    public function test_revoke_token_failure(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser($user);

        $this->artisan('app:token', ['--revoke' => true])
            ->expectsOutput('You are about to revoke an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('No tokens found for this user.')
            ->expectsQuestion('Enter the token ID to revoke', '999')
            ->expectsOutput('Token successfully revoked.')
            ->assertExitCode(0);
    }

    public function test_create_token_failure(): void
    {
        $user = $this->createUser();
        $this->bindTokenServiceForUser(null);

        $this->artisan('app:token')
            ->expectsOutput('You are about to create an API token for a user.')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsQuestion('Enter a name for the token (max 16 characters)', 'test-token')
            ->expectsOutput('Failed to create token. Failed to execute method App\Services\Profile\ApiTokenService::createApiToken, because it requires a currently authenticated user.')
            ->assertExitCode(0);
    }
}
