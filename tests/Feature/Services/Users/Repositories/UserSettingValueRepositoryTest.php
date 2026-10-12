<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Users\Repositories;

use App\Models\User;
use App\Services\Users\Repositories\UserSettingValueRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserSettingValueRepository::class)]
class UserSettingValueRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    private UserSettingValueRepository $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->app->make(UserSettingValueRepository::class);
    }

    // =========================================================================
    // Per-user access
    // =========================================================================

    public function testItPersistForUserUpsertsAndReadsRawRowsPerUser(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark', 'timezone' => 'Europe/Berlin']);
        $this->givenRows($other, 'hawki-core', ['theme' => 'light']);

        self::assertSame(
            ['theme' => 'dark', 'timezone' => 'Europe/Berlin'],
            $this->sut->getRawRowsForUser($user, 'hawki-core'),
        );

        // Upserts overwrite existing rows of the same user and namespace.
        $this->sut->persistForUser($user, 'hawki-core', ['theme' => 'light'], []);

        self::assertSame(
            ['theme' => 'light', 'timezone' => 'Europe/Berlin'],
            $this->sut->getRawRowsForUser($user, 'hawki-core'),
        );
    }

    public function testItPerUserRowsStayConfinedToTheirUser(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark']);

        self::assertSame([], $this->sut->getRawRowsForUser($other, 'hawki-core'));
    }

    public function testItPersistForUserRemovesOnlyTheGivenKeys(): void
    {
        $user = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark', 'timezone' => 'Europe/Berlin']);
        $this->sut->persistForUser($user, 'hawki-core', [], ['theme']);

        self::assertSame(
            ['timezone' => 'Europe/Berlin'],
            $this->sut->getRawRowsForUser($user, 'hawki-core'),
        );
    }

    public function testItPersistForUserAppliesUpsertsAndRemovalsInOneCall(): void
    {
        $user = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark', 'timezone' => 'Europe/Berlin']);
        $this->sut->persistForUser($user, 'hawki-core', ['timezone' => 'UTC'], ['theme']);

        self::assertSame(
            ['timezone' => 'UTC'],
            $this->sut->getRawRowsForUser($user, 'hawki-core'),
        );
    }

    public function testItPersistForUserIsANoOpWhenNothingChanged(): void
    {
        $user = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark']);
        $this->sut->persistForUser($user, 'hawki-core', [], []);

        self::assertSame(['theme' => 'dark'], $this->sut->getRawRowsForUser($user, 'hawki-core'));
    }

    public function testItDeleteAllForUserRemovesRowsAcrossAllNamespaces(): void
    {
        $user = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark']);
        $this->givenRows($user, 'other-namespace', ['key' => 'value']);

        $this->sut->deleteAllForUser($user);

        self::assertSame([], $this->sut->getRawRowsForUser($user, 'hawki-core'));
        self::assertSame([], $this->sut->getRawRowsForUser($user, 'other-namespace'));
    }

    // =========================================================================
    // Global access (migration tooling)
    // =========================================================================

    public function testItGetUserIdsForNamespaceLazyReturnsDistinctUserIds(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Two rows for $user (still one id), one row for $other, none for a third user.
        $this->givenRows($user, 'hawki-core', ['theme' => 'dark', 'timezone' => 'UTC']);
        $this->givenRows($other, 'hawki-core', ['theme' => 'light']);
        $this->givenRows($other, 'other-namespace', ['key' => 'value']);

        $ids = $this->sut->getUserIdsForNamespaceLazy('hawki-core')->values()->all();

        self::assertCount(2, $ids);
        self::assertContains($user->id, $ids);
        self::assertContains($other->id, $ids);
    }

    public function testItUpsertValueForUserIdAndReadRawRowsByUserId(): void
    {
        $user = User::factory()->create();

        $this->sut->upsertValueForUserId($user->id, 'hawki-core', 'theme', 'dark');

        self::assertSame(['theme' => 'dark'], $this->sut->getRawRowsForUserId($user->id, 'hawki-core'));
    }

    public function testItDeleteForNamespaceAndKeyRemovesTheKeyForAllUsers(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark', 'timezone' => 'UTC']);
        $this->givenRows($other, 'hawki-core', ['theme' => 'light', 'timezone' => 'UTC']);

        $this->sut->deleteForNamespaceAndKey('hawki-core', 'theme');

        self::assertSame(['timezone' => 'UTC'], $this->sut->getRawRowsForUserId($user->id, 'hawki-core'));
        self::assertSame(['timezone' => 'UTC'], $this->sut->getRawRowsForUserId($other->id, 'hawki-core'));
    }

    public function testItDeleteForNamespaceRemovesTheWholeNamespaceForAllUsers(): void
    {
        $user = User::factory()->create();

        $this->givenRows($user, 'hawki-core', ['theme' => 'dark']);
        $this->givenRows($user, 'other-namespace', ['key' => 'value']);

        $this->sut->deleteForNamespace('hawki-core');

        self::assertSame([], $this->sut->getRawRowsForUserId($user->id, 'hawki-core'));
        self::assertSame(['key' => 'value'], $this->sut->getRawRowsForUserId($user->id, 'other-namespace'));
    }

    public function testItRenameNamespaceMovesAllUsersRowsAtOnce(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->givenRows($user, 'old-namespace', ['theme' => 'dark']);
        $this->givenRows($other, 'old-namespace', ['theme' => 'light']);

        $this->sut->renameNamespace('old-namespace', 'new-namespace');

        self::assertSame(['theme' => 'dark'], $this->sut->getRawRowsForUserId($user->id, 'new-namespace'));
        self::assertSame(['theme' => 'light'], $this->sut->getRawRowsForUserId($other->id, 'new-namespace'));
        self::assertSame([], $this->sut->getRawRowsForUserId($user->id, 'old-namespace'));
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * @param array<string, null|string> $rows
     */
    private function givenRows(User $user, string $namespace, array $rows): void
    {
        $this->sut->persistForUser($user, $namespace, $rows, []);
    }
}
