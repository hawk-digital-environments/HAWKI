<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Users\Listeners;

use App\Models\Organization;
use App\Models\User;
use App\Services\Users\Events\UserCreatedEvent;
use App\Services\Users\Listeners\AssignDefaultOrganizationForNewUser;
use App\Services\Users\Repositories\UserOrganizationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(AssignDefaultOrganizationForNewUser::class)]
class AssignDefaultOrganizationForNewUserTest extends TestCase
{
    // =========================================================================
    // handle
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new AssignDefaultOrganizationForNewUser(
            $this->createMock(UserOrganizationRepository::class),
            'HAWKI',
            $this->createMock(LoggerInterface::class)
        );
        static::assertInstanceOf(AssignDefaultOrganizationForNewUser::class, $sut);
    }

    public function testItAttachesTheCreatedUserToTheDefaultOrganization(): void
    {
        $user = $this->createMock(User::class);
        $organization = $this->createMock(Organization::class);

        $repository = $this->createMock(UserOrganizationRepository::class);
        $repository->method('findByName')->with('HAWKI')->willReturn($organization);
        $repository->expects($this->once())
            ->method('attachUserAsMember')
            ->with($user, $organization);

        $sut = new AssignDefaultOrganizationForNewUser(
            $repository,
            'HAWKI',
            $this->createMock(LoggerInterface::class)
        );
        $sut->handle(new UserCreatedEvent($user));
    }

    public function testItSkipsTheAssignmentWhenNoDefaultOrganizationIsConfigured(): void
    {
        $repository = $this->createMock(UserOrganizationRepository::class);
        $repository->expects($this->never())->method('findByName');
        $repository->expects($this->never())->method('attachUserAsMember');

        $sut = new AssignDefaultOrganizationForNewUser(
            $repository,
            '',
            $this->createMock(LoggerInterface::class)
        );
        $sut->handle(new UserCreatedEvent($this->createMock(User::class)));
    }

    public function testItSkipsTheAssignmentAndLogsAWarningWhenTheDefaultOrganizationDoesNotExist(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $repository = $this->createMock(UserOrganizationRepository::class);
        $repository->method('findByName')->willReturn(null);
        $repository->expects($this->never())->method('attachUserAsMember');

        $sut = new AssignDefaultOrganizationForNewUser(
            $repository,
            'HAWKI',
            $logger
        );
        $sut->handle(new UserCreatedEvent($this->createMock(User::class)));
    }
}
