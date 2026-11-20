<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

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

    private const CONFIRM_QUESTION = 'The user and all the related messages will be permanently removed. Do you want to continue?';

    private const IDENTIFY_QUESTION = 'How would you like to identify the user?';

    private const IDENTIFY_CHOICES = ['Username', 'Email Address', 'UserID'];

    protected function setUp(): void
    {
        parent::setUp();

        // ProfileService::deleteUserData() resolves its collaborators through
        // the service locator, which disables container fallback under
        // PHPUnit — bind a fallback-enabled instance for the command.
        $this->app->instance(
            ProfileService::class,
            app(ProfileService::class)->useServiceContainerFallback(true),
        );
    }

    public function testItRemovesAnActiveUserByUsername(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:removeuser')
            ->expectsConfirmation(self::CONFIRM_QUESTION, 'yes')
            ->expectsChoice(self::IDENTIFY_QUESTION, 'Username', self::IDENTIFY_CHOICES)
            ->expectsQuestion('Please enter the Username', $user->username)
            ->expectsOutput('Profile Reset Successful!')
            ->assertExitCode(0);

        static::assertDatabaseHas('users', ['id' => $user->id, 'isRemoved' => 1]);
    }

    public function testItRemovesAnActiveUserByEmail(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:removeuser')
            ->expectsConfirmation(self::CONFIRM_QUESTION, 'yes')
            ->expectsChoice(self::IDENTIFY_QUESTION, 'Email Address', self::IDENTIFY_CHOICES)
            ->expectsQuestion('Please enter the Email Address', $user->email)
            ->expectsOutput('Profile Reset Successful!')
            ->assertExitCode(0);

        static::assertDatabaseHas('users', ['id' => $user->id, 'isRemoved' => 1]);
    }

    public function testItAbortsWhenTheUserIsNotFound(): void
    {
        $this->artisan('app:removeuser')
            ->expectsConfirmation(self::CONFIRM_QUESTION, 'yes')
            ->expectsChoice(self::IDENTIFY_QUESTION, 'Username', self::IDENTIFY_CHOICES)
            ->expectsQuestion('Please enter the Username', 'no-such-user')
            ->expectsOutput('User not found!')
            ->assertExitCode(0);

        static::assertDatabaseMissing('users', ['username' => 'no-such-user']);
    }

    public function testItCancelsWithoutConfirmation(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:removeuser')
            ->expectsConfirmation(self::CONFIRM_QUESTION, 'no')
            ->expectsOutput('Command operation cancelled.')
            ->assertExitCode(0);

        static::assertDatabaseHas('users', ['id' => $user->id, 'isRemoved' => 0]);
    }
}
