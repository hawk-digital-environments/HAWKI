<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * End-to-end test of the `user-favorites` JSON:API resource: reads, idempotent
 * writes, user isolation and auth gating through the whole stack (route auth,
 * schema, controller hook, service, repository).
 */
#[CoversNothing()]
class UserFavoritesApiTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Auth gating
    // =========================================================================

    public function testItRequiresAuthenticationForTheIndex(): void
    {
        $this->getJson('/api/hawki/v1/user-favorites', $this->jsonApiHeaders())
            ->assertUnauthorized();
    }

    public function testItRequiresAuthenticationForStore(): void
    {
        $this->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument('ai-model', 'gpt-4o'),
            $this->jsonApiHeaders(),
        )->assertUnauthorized();
    }

    // =========================================================================
    // Reads
    // =========================================================================

    public function testItIndexesOnlyTheCallingUsersFavorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->storeFavorite($user, 'ai-model', 'gpt-4o');
        $this->storeFavorite($other, 'ai-model', 'claude');

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/user-favorites', $this->jsonApiHeaders());

        $response->assertOk();

        $identifiers = array_map(
            static fn (array $resource): string => $resource['attributes']['identifier'],
            $response->json('data'),
        );

        self::assertSame(['gpt-4o'], $identifiers);
    }

    public function testItIndexesSupportsTypeAndNamespaceFilters(): void
    {
        $user = User::factory()->create();

        $this->storeFavorite($user, 'ai-model', 'gpt-4o');
        $this->storeFavorite($user, 'room', 'room-slug');

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/user-favorites?filter[item_type]=room', $this->jsonApiHeaders());

        $response->assertOk();

        $types = array_map(
            static fn (array $resource): string => $resource['attributes']['item_type'],
            $response->json('data'),
        );

        self::assertSame(['room'], $types);
    }

    // =========================================================================
    // Writes
    // =========================================================================

    public function testItStoresFavoritesForTheAuthenticatedUserWithTheDefaultNamespace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument('ai-model', 'gpt-4o'),
            $this->jsonApiHeaders(),
        );

        $response->assertCreated();
        self::assertSame('hawki-core', $response->json('data.attributes.namespace'));
        self::assertSame('ai-model', $response->json('data.attributes.item_type'));
        self::assertSame('gpt-4o', $response->json('data.attributes.identifier'));
        self::assertArrayNotHasKey('user_id', $response->json('data.attributes'));

        $this->assertDatabaseHas('user_favorite_values', [
            'user_id' => $user->id,
            'namespace' => 'hawki-core',
            'type' => 'ai-model',
            'identifier' => 'gpt-4o',
        ]);
    }

    public function testItStoreIsIdempotentForDuplicates(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument('ai-model', 'gpt-4o'),
            $this->jsonApiHeaders(),
        );
        $second = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument('ai-model', 'gpt-4o'),
            $this->jsonApiHeaders(),
        );

        $first->assertCreated();
        $second->assertCreated();
        self::assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('user_favorite_values', 1);
    }

    public function testItStoreAcceptsAnExplicitNamespace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument('ai-model', 'gpt-4o', 'legacy-ns'),
            $this->jsonApiHeaders(),
        );

        $response->assertCreated();
        self::assertSame('legacy-ns', $response->json('data.attributes.namespace'));
    }

    public function testItStoreRejectsMissingType(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            [
                'data' => [
                    'type' => 'user-favorites',
                    'attributes' => ['identifier' => 'gpt-4o'],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('user_favorite_values', 0);
    }

    public function testItDestroysTheAddressedFavorite(): void
    {
        $user = User::factory()->create();
        $id = $this->storeFavorite($user, 'ai-model', 'gpt-4o');

        $this->actingAs($user)
            ->deleteJson("/api/hawki/v1/user-favorites/{$id}", [], $this->jsonApiHeaders())
            ->assertNoContent();

        $this->assertDatabaseMissing('user_favorite_values', [
            'user_id' => $user->id,
            'identifier' => 'gpt-4o',
        ]);
    }

    public function testItDestroyIsConfinedToTheCallingUsersRows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignId = $this->storeFavorite($other, 'ai-model', 'claude');

        $this->actingAs($user)
            ->deleteJson("/api/hawki/v1/user-favorites/{$foreignId}", [], $this->jsonApiHeaders())
            ->assertNotFound();

        $this->assertDatabaseHas('user_favorite_values', ['id' => $foreignId]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function storeFavorite(User $user, string $type, string $identifier): string
    {
        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/user-favorites',
            $this->storeDocument($type, $identifier),
            $this->jsonApiHeaders(),
        );
        $response->assertCreated();

        return (string) $response->json('data.id');
    }

    private function storeDocument(string $type, string $identifier, ?string $namespace = null): array
    {
        return [
            'data' => [
                'type' => 'user-favorites',
                'attributes' => [
                    'namespace' => $namespace,
                    'item_type' => $type,
                    'identifier' => $identifier,
                ],
            ],
        ];
    }

    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json,application/json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
