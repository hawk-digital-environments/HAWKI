<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * End-to-end test of the `user-settings` JSON:API resource: reads, writes, guest
 * session persistence and validation failures through the whole stack (schema,
 * repository, capabilities, request validation, service, diff-based storage).
 */
#[CoversNothing()]
class UserSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Reads
    // =========================================================================

    public function testItShowsTheNamespaceResourceForAuthenticatedUsers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/user-settings/hawki-core', $this->jsonApiHeaders());

        $response->assertOk();
        self::assertSame('hawki-core', $response->json('data.id'));
        self::assertSame('light', $response->json('data.attributes.core.theme'));
        self::assertNull($response->json('data.attributes.core.locale'));
        self::assertSame('UTC', $response->json('data.attributes.core.timezone'));
    }

    public function testItIndexesAllNamespacesAsAList(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/user-settings', $this->jsonApiHeaders());

        $response->assertOk();

        $ids = array_map(
            static fn (array $resource): string => $resource['id'],
            $response->json('data'),
        );

        self::assertContains('hawki-core', $ids);
    }

    public function testItUnknownNamespacesAreNotFound(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/user-settings/unknown-namespace', $this->jsonApiHeaders());

        $response->assertNotFound();
    }

    // =========================================================================
    // Writes (authenticated users → database storage)
    // =========================================================================

    public function testItPatchesSettingsForAuthenticatedUsersIntoTheDatabase(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['theme' => 'dark'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertOk();
        self::assertSame('dark', $response->json('data.attributes.core.theme'));

        // Sparse storage: only the customized key has a row.
        $this->assertDatabaseHas('user_setting_values', [
            'user_id' => $user->id,
            'namespace' => 'hawki-core',
            'key' => 'theme',
            'value' => 'dark',
        ]);
        $this->assertDatabaseMissing('user_setting_values', [
            'user_id' => $user->id,
            'key' => 'timezone',
        ]);
    }

    public function testItPatchKeepsMissingAttributesAtTheirCurrentValues(): void
    {
        $user = User::factory()->create();

        $this->patchTheme($user, 'dark');
        $response = $this->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['timezone' => 'Europe/Berlin'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertOk();
        // The untouched theme kept its previous value, the touched timezone changed.
        self::assertSame('dark', $response->json('data.attributes.core.theme'));
        self::assertSame('Europe/Berlin', $response->json('data.attributes.core.timezone'));
    }

    public function testItPatchRejectsUnknownEnumValues(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['theme' => 'purple'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('user_setting_values', [
            'user_id' => $user->id,
        ]);
    }

    public function testItPatchIgnoresUnAnnotatedProperties(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['not-a-real-property' => 'x'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertOk();
        $this->assertDatabaseMissing('user_setting_values', [
            'user_id' => $user->id,
        ]);
    }

    // =========================================================================
    // Writes (guests → session storage)
    // =========================================================================

    public function testItPatchesGuestSettingsIntoTheSessionWithoutDatabaseRows(): void
    {
        $response = $this->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['theme' => 'dark'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response->assertOk();
        self::assertSame('dark', $response->json('data.attributes.core.theme'));

        // Guest settings live in the session, never in the database.
        $this->assertDatabaseMissing('user_setting_values', ['key' => 'theme']);

        // And the session-persisted value is still served on the next request.
        $followUp = $this->getJson('/api/hawki/v1/user-settings/hawki-core', $this->jsonApiHeaders());

        $followUp->assertOk();
        self::assertSame('dark', $followUp->json('data.attributes.core.theme'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function patchTheme(User $user, string $theme): void
    {
        $this->actingAs($user)->patchJson(
            '/api/hawki/v1/user-settings/hawki-core',
            [
                'data' => [
                    'type' => 'user-settings',
                    'id' => 'hawki-core',
                    'attributes' => [
                        'core' => ['theme' => $theme],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        )->assertOk();
    }

    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json,application/json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
