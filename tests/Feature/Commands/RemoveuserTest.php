<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\Removeuser;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Removeuser::class)]
class RemoveuserTest extends TestCase
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

    public function test_cancel_operation(): void
    {
        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'no')
            ->expectsOutput('Command operation cancelled.')
            ->assertExitCode(0);
    }

    public function test_remove_user_by_username(): void
    {
        $user = $this->createUser();

        $this->mock(ProfileService::class)
            ->shouldReceive('deleteUserData')
            ->once()
            ->with(\Mockery::on(fn(User $u) => $u->id === $user->id));

        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'yes')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('Profile Reset Successfull!')
            ->assertExitCode(0);
    }

    public function test_remove_user_by_email(): void
    {
        $user = $this->createUser();

        $this->mock(ProfileService::class)
            ->shouldReceive('deleteUserData')
            ->once()
            ->with(\Mockery::on(fn(User $u) => $u->id === $user->id));

        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'yes')
            ->expectsChoice('How would you like to identify the user?', 'Email Address', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Email Address', 'test@example.com')
            ->expectsOutput('Profile Reset Successfull!')
            ->assertExitCode(0);
    }

    public function test_remove_user_by_userid(): void
    {
        $user = $this->createUser();

        $this->mock(ProfileService::class)
            ->shouldReceive('deleteUserData')
            ->once()
            ->with(\Mockery::on(fn(User $u) => $u->id === $user->id));

        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'yes')
            ->expectsChoice('How would you like to identify the user?', 'UserID', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the UserID', (string) $user->id)
            ->expectsOutput('Profile Reset Successfull!')
            ->assertExitCode(0);
    }

    public function test_user_not_found(): void
    {
        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'yes')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'nonexistent')
            ->expectsOutput('User not found!')
            ->assertExitCode(0);
    }

    public function test_user_already_removed(): void
    {
        $this->createUser(['isRemoved' => true]);

        $this->artisan('app:removeuser')
            ->expectsConfirmation('The user and all the related messages will be permanently removed. Do you want to continue?', 'yes')
            ->expectsChoice('How would you like to identify the user?', 'Username', ['Username', 'Email Address', 'UserID'])
            ->expectsQuestion('Please enter the Username', 'testuser')
            ->expectsOutput('User is already removed!')
            ->assertExitCode(0);
    }
}
