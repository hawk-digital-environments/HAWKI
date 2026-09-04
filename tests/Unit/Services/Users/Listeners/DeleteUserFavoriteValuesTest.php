<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Listeners;

use App\Models\User;
use App\Services\Users\Events\UserRemovedEvent;
use App\Services\Users\Listeners\DeleteUserFavoriteValues;
use App\Services\Users\Repositories\UserFavoriteValueRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(DeleteUserFavoriteValues::class)]
class DeleteUserFavoriteValuesTest extends TestCase
{
    private DeleteUserFavoriteValues $sut;
    private MockObject&UserFavoriteValueRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(UserFavoriteValueRepository::class);
        $this->sut = new DeleteUserFavoriteValues($this->repository);
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(DeleteUserFavoriteValues::class, $this->sut);
    }

    public function testItDeletesAllFavoritesOfTheRemovedUser(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new UserRemovedEvent($user);

        $this->repository->expects($this->once())
            ->method('deleteAllForUser')
            ->with($user);

        $this->sut->handle($event);
    }
}
