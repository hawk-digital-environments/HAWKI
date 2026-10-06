<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Users\Repositories;

use App\Models\User;
use App\Services\Users\Repositories\UserFavoriteValueRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserFavoriteValueRepository::class)]
class UserFavoriteValueRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    private UserFavoriteValueRepository $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->app->make(UserFavoriteValueRepository::class);
    }

    // =========================================================================
    // Create (idempotent)
    // =========================================================================

    public function testItCreatesFavoritesForTheUser(): void
    {
        $user = User::factory()->create();

        $favorite = $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');

        self::assertSame('ai-model', $favorite->type);
        self::assertSame('gpt-4o', $favorite->identifier);
        self::assertSame('hawki-core', $favorite->namespace);
        self::assertSame($user->id, $favorite->user_id);

        $this->assertDatabaseHas('user_favorite_values', [
            'user_id' => $user->id,
            'namespace' => 'hawki-core',
            'type' => 'ai-model',
            'identifier' => 'gpt-4o',
        ]);
    }

    public function testItCreateIsIdempotentForDuplicateQuadruples(): void
    {
        $user = User::factory()->create();

        $first = $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');
        $second = $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');

        self::assertTrue($first->is($second));
        $this->assertDatabaseCount('user_favorite_values', 1);
    }

    // =========================================================================
    // Reads
    // =========================================================================

    public function testItGetForUserReturnsOnlyTheCallingUsersRows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');
        $this->sut->createForUser($other, 'ai-model', 'claude', 'hawki-core');

        $favorites = $this->sut->getForUser($user);

        self::assertCount(1, $favorites);
        self::assertSame('gpt-4o', $favorites->first()->identifier);
    }

    public function testItGetForUserFiltersByTypeAndNamespace(): void
    {
        $user = User::factory()->create();

        $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');
        $this->sut->createForUser($user, 'room', 'room-slug', 'hawki-core');
        $this->sut->createForUser($user, 'ai-model', 'legacy-model', 'legacy-namespace');

        self::assertCount(1, $this->sut->getForUser($user, 'room'));
        self::assertCount(1, $this->sut->getForUser($user, null, 'legacy-namespace'));
        self::assertCount(1, $this->sut->getForUser($user, 'ai-model', 'hawki-core'));
        self::assertCount(3, $this->sut->getForUser($user));
    }

    public function testItExistsForUserChecksTheExactQuadruple(): void
    {
        $user = User::factory()->create();

        $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');

        self::assertTrue($this->sut->existsForUser($user, 'ai-model', 'gpt-4o', 'hawki-core'));
        self::assertFalse($this->sut->existsForUser($user, 'ai-model', 'claude', 'hawki-core'));
        self::assertFalse($this->sut->existsForUser($user, 'ai-model', 'gpt-4o', 'other-ns'));

        $other = User::factory()->create();
        self::assertFalse($this->sut->existsForUser($other, 'ai-model', 'gpt-4o', 'hawki-core'));
    }

    // =========================================================================
    // Deletes
    // =========================================================================

    public function testItDeleteForUserRemovesOnlyTheAddressedQuadruple(): void
    {
        $user = User::factory()->create();

        $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');
        $this->sut->createForUser($user, 'ai-model', 'claude', 'hawki-core');

        self::assertTrue($this->sut->deleteForUser($user, 'ai-model', 'gpt-4o', 'hawki-core'));
        self::assertFalse($this->sut->existsForUser($user, 'ai-model', 'gpt-4o', 'hawki-core'));
        self::assertTrue($this->sut->existsForUser($user, 'ai-model', 'claude', 'hawki-core'));
    }

    public function testItDeleteForUserReturnsFalseForAbsentFavorites(): void
    {
        $user = User::factory()->create();

        self::assertFalse($this->sut->deleteForUser($user, 'ai-model', 'gpt-4o', 'hawki-core'));
    }

    public function testItDeleteAllForUserRemovesRowsAcrossAllNamespacesAndTypes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->sut->createForUser($user, 'ai-model', 'gpt-4o', 'hawki-core');
        $this->sut->createForUser($user, 'room', 'room-slug', 'other-ns');
        $this->sut->createForUser($other, 'ai-model', 'claude', 'hawki-core');

        $this->sut->deleteAllForUser($user);

        self::assertCount(0, $this->sut->getForUser($user));
        self::assertCount(1, $this->sut->getForUser($other));
    }
}
