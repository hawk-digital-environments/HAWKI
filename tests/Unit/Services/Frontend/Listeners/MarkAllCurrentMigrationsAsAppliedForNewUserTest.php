<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Listeners;

use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\User;
use App\Services\Frontend\Listeners\MarkAllCurrentMigrationsAsAppliedForNewUser;
use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Users\Events\UserCreatedEvent;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MarkAllCurrentMigrationsAsAppliedForNewUser::class)]
class MarkAllCurrentMigrationsAsAppliedForNewUserTest extends TestCase
{
    // =========================================================================
    // handle
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new MarkAllCurrentMigrationsAsAppliedForNewUser(
            $this->createMock(FrontendMigrationRepository::class),
            $this->createMock(AppliedFrontendMigrationRepository::class)
        );
        static::assertInstanceOf(MarkAllCurrentMigrationsAsAppliedForNewUser::class, $sut);
    }

    public function testItCallsApplyAllForNewUserWithAllMigrationsAndTheEventUser(): void
    {
        $user = $this->createMock(User::class);
        $migrations = new Collection([
            $this->createMock(FrontendMigration::class),
            $this->createMock(FrontendMigration::class),
        ]);

        $migrationRepository = $this->createMock(FrontendMigrationRepository::class);
        $migrationRepository->method('findAll')->willReturn($migrations);

        $appliedRepository = $this->createMock(AppliedFrontendMigrationRepository::class);
        $appliedRepository->expects($this->once())
            ->method('applyAllForNewUser')
            ->with($migrations, $user);

        $sut = new MarkAllCurrentMigrationsAsAppliedForNewUser($migrationRepository, $appliedRepository);
        $sut->handle(new UserCreatedEvent($user));
    }

    public function testItFetchesAllMigrationsBeforeApplying(): void
    {
        $user = $this->createMock(User::class);
        $migrations = new Collection();

        $migrationRepository = $this->createMock(FrontendMigrationRepository::class);
        $migrationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($migrations);

        $appliedRepository = $this->createMock(AppliedFrontendMigrationRepository::class);
        $appliedRepository->method('applyAllForNewUser');

        $sut = new MarkAllCurrentMigrationsAsAppliedForNewUser($migrationRepository, $appliedRepository);
        $sut->handle(new UserCreatedEvent($user));
    }

    public function testItWorksWhenNoMigrationsExist(): void
    {
        $user = $this->createMock(User::class);
        $emptyCollection = new Collection();

        $migrationRepository = $this->createMock(FrontendMigrationRepository::class);
        $migrationRepository->method('findAll')->willReturn($emptyCollection);

        $appliedRepository = $this->createMock(AppliedFrontendMigrationRepository::class);
        $appliedRepository->expects($this->once())
            ->method('applyAllForNewUser')
            ->with($emptyCollection, $user);

        $sut = new MarkAllCurrentMigrationsAsAppliedForNewUser($migrationRepository, $appliedRepository);
        $sut->handle(new UserCreatedEvent($user));
    }
}
