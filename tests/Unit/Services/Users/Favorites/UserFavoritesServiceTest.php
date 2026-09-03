<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Favorites;

use App\Models\User;
use App\Models\UserFavoriteValue;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use App\Services\Users\Exceptions\MissingAuthenticatedUserException;
use App\Services\Users\Favorites\UserFavoritesService;
use App\Services\Users\Repositories\UserFavoriteValueRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(UserFavoritesService::class)]
class UserFavoritesServiceTest extends TestCase
{
    private UserFavoritesService $sut;
    private MockObject&UserContext $userContext;
    private MockObject&UserFavoriteValueRepository $repository;
    private ?User $currentUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userContext = $this->createMock(UserContext::class);
        $this->repository = $this->createMock(UserFavoriteValueRepository::class);

        // Configured once: always hands out the *current* user, so tests can
        // simulate user switches without re-stubbing (PHPUnit's first matching
        // stub wins).
        $this->userContext->method('getUser')->willReturnCallback(fn (): ?User => $this->currentUser);

        $this->sut = new UserFavoritesService($this->userContext, $this->repository);
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(UserFavoritesService::class, $this->sut);
    }

    // =========================================================================
    // Guest handling
    // =========================================================================

    public function testItIsFavoriteThrowsForGuests(): void
    {
        $this->givenGuest();
        $this->repository->expects($this->never())->method('getForUser');

        try {
            $this->sut->isFavorite('ai-model', 'gpt-4o');
            self::fail('Expected MissingAuthenticatedUserException was not thrown.');
        } catch (MissingAuthenticatedUserException $exception) {
            self::assertSame(
                'Cannot access favorites without an authenticated user. Favorites are'
                . ' stored per user account; guests and registering users have none.'
                . ' Log in to manage favorites.',
                $exception->getMessage(),
            );
        }
    }

    public function testItMarkAsFavoriteThrowsForGuests(): void
    {
        $this->givenGuest();
        $this->repository->expects($this->never())->method('createForUser');

        try {
            $this->sut->markAsFavorite('ai-model', 'gpt-4o');
            self::fail('Expected MissingAuthenticatedUserException was not thrown.');
        } catch (MissingAuthenticatedUserException $exception) {
            self::assertSame(
                'Cannot access favorites without an authenticated user. Favorites are'
                . ' stored per user account; guests and registering users have none.'
                . ' Log in to manage favorites.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRemoveAsFavoriteThrowsForRegisteringUsers(): void
    {
        // getUser() returns a RegisteringUser — not a User — for registering users.
        // A non-User identity shape must throw just like null (guests).
        $this->userContext->method('getUser')->willReturn(new RegisteringUser('jane.doe', 'Jane Doe', 'jane.doe@example.org', 'staff'));
        $this->repository->expects($this->never())->method('deleteForUser');

        try {
            $this->sut->removeAsFavorite('ai-model', 'gpt-4o');
            self::fail('Expected MissingAuthenticatedUserException was not thrown.');
        } catch (MissingAuthenticatedUserException $exception) {
            self::assertSame(
                'Cannot access favorites without an authenticated user. Favorites are'
                . ' stored per user account; guests and registering users have none.'
                . ' Log in to manage favorites.',
                $exception->getMessage(),
            );
        }
    }

    // =========================================================================
    // isFavorite
    // =========================================================================

    public function testItIsFavoriteReflectsTheUsersStoredFavorites(): void
    {
        $user = $this->givenUser(42);
        $this->repository->method('getForUser')->willReturn($this->favoritesCollection($this->makeFavorite($user, 'hawki-core', 'ai-model', 'gpt-4o')));

        self::assertTrue($this->sut->isFavorite('ai-model', 'gpt-4o'));
        self::assertFalse($this->sut->isFavorite('ai-model', 'claude'));
        self::assertFalse($this->sut->isFavorite('room', 'gpt-4o'));
    }

    public function testItIsFavoriteOnlyKnowsTheRequestedNamespace(): void
    {
        $user = $this->givenUser(42);
        $this->repository->method('getForUser')->willReturn($this->favoritesCollection($this->makeFavorite($user, 'legacy-ns', 'ai-model', 'gpt-4o')));

        self::assertFalse($this->sut->isFavorite('ai-model', 'gpt-4o'));
        self::assertTrue($this->sut->isFavorite('ai-model', 'gpt-4o', 'legacy-ns'));
    }

    // =========================================================================
    // markAsFavorite / removeAsFavorite
    // =========================================================================

    public function testItMarkAsFavoritePassesTheResolvedNamespaceAndRefreshesTheCache(): void
    {
        $user = $this->givenUser(42);
        $created = $this->makeFavorite($user, 'hawki-core', 'ai-model', 'gpt-4o');

        $this->repository->expects($this->once())
            ->method('createForUser')
            ->with($user, 'ai-model', 'gpt-4o', 'hawki-core')
            ->willReturn($created);

        $result = $this->sut->markAsFavorite('ai-model', 'gpt-4o');

        self::assertTrue($result->is($created));
        // The identity map must now know the new favorite without a repository round-trip.
        self::assertTrue($this->sut->isFavorite('ai-model', 'gpt-4o'));
    }

    public function testItRemoveAsFavoriteIsANoOpForAbsentFavorites(): void
    {
        $user = $this->givenUser(42);
        $this->repository->expects($this->once())
            ->method('deleteForUser')
            ->with($user, 'ai-model', 'gpt-4o', 'hawki-core')
            ->willReturn(false);

        $this->sut->removeAsFavorite('ai-model', 'gpt-4o');

        // No exception, cache entry untouched.
        self::assertFalse($this->sut->isFavorite('ai-model', 'gpt-4o'));
    }

    // =========================================================================
    // Identity map (user-switch safety)
    // =========================================================================

    public function testItFavoritesDoNotLeakAcrossUserSwitches(): void
    {
        $first = $this->givenUser(42);
        $firstFavorite = $this->makeFavorite($first, 'hawki-core', 'ai-model', 'first-users-model');
        $secondFavorite = $this->makeFavorite($first, 'hawki-core', 'ai-model', 'second-users-model');

        // Serve each user its own favorite set — a stub would not distinguish callers.
        $this->repository->method('getForUser')->willReturnCallback(fn (User $user): \Illuminate\Database\Eloquent\Collection => $this->favoritesCollection(42 === $user->id ? $firstFavorite : $secondFavorite));

        self::assertTrue($this->sut->isFavorite('ai-model', 'first-users-model'));

        $this->givenUser(7);

        // A user switch in a long-lived process must re-key the identity map.
        self::assertFalse($this->sut->isFavorite('ai-model', 'first-users-model'));
        self::assertTrue($this->sut->isFavorite('ai-model', 'second-users-model'));
    }

    // =========================================================================
    // getFavorites
    // =========================================================================

    public function testItGetFavoritesPassesTypeAndNamespaceThrough(): void
    {
        $user = $this->givenUser(42);
        $expected = $this->favoritesCollection($this->makeFavorite($user, 'legacy-ns', 'room', 'room-slug'));

        $this->repository->expects($this->once())
            ->method('getForUser')
            ->with($user, 'room', 'legacy-ns')
            ->willReturn($expected);

        self::assertSame($expected, $this->sut->getFavorites('room', 'legacy-ns'));
    }

    public function testItGetFavoritesPassesNullThroughForAnUnfilteredList(): void
    {
        $user = $this->givenUser(42);
        $expected = $this->favoritesCollection();

        $this->repository->expects($this->once())
            ->method('getForUser')
            ->with($user, null, null)
            ->willReturn($expected);

        self::assertSame($expected, $this->sut->getFavorites());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function givenUser(int $id): User
    {
        // Direct property assignment: `id` is not mass-assignable, but the identity
        // map in the service keys on it, so the tests need distinct ids.
        $user = User::factory()->make();
        $user->id = $id;
        $this->currentUser = $user;

        return $user;
    }

    private function givenGuest(): void
    {
        $this->currentUser = null;
    }

    private function makeFavorite(User $user, string $namespace, string $type, string $identifier): UserFavoriteValue
    {
        $favorite = new UserFavoriteValue();
        $favorite->forceFill([
            'user_id' => $user->id,
            'namespace' => $namespace,
            'type' => $type,
            'identifier' => $identifier,
        ]);

        return $favorite;
    }

    /**
     * Wraps favorites in the Eloquent Collection type the repository contract declares.
     */
    private function favoritesCollection(UserFavoriteValue ...$favorites): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, UserFavoriteValue> */
        return (new UserFavoriteValue())->newCollection($favorites);
    }
}
