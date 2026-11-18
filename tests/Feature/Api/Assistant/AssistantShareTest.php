<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantShareTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerCanListSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users")
            ->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id');
        self::assertContains((string) $alice->id, $ids);
    }

    public function testNonOwnerCannotListSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users")
            ->assertForbidden();
    }

    /**
     * Share writes live on actions/share alone: POST/PATCH/DELETE on the
     * relationship are not routes (only the read-only GETs remain).
     */
    public function testSharedUsersRelationshipWritesHaveNoRoutes(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $target = User::factory()->create();

        $this->actingAsUser($owner);

        $uri = "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users";
        $payload = ['data' => [['type' => 'users', 'id' => (string) $target->id]]];

        $this->jsonApiRaw('post', $uri, $payload)->assertStatus(405);
        $this->jsonApiRaw('patch', $uri, $payload)->assertStatus(405);
        $this->jsonApiRaw('delete', $uri, $payload)->assertStatus(405);

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function testSharedUserCanListPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testSharedUserCanShowPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function testNonSharedUserCannotListPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testNonSharedUserCannotShowPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testSharedUserCanListDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testSharedUserCanShowDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function testDetachingRevokesVisibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [],
        ])->assertSuccessful();

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    // --- actions/share: list + sync ---

    public function testCreatorCanListShares(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $assistant->sharedUsers()->sync([$alice->id => ['can_edit' => true]]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/share")
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'type' => 'users',
                        'id' => (string) $alice->id,
                        'meta' => ['can_edit' => true],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'users',
                        'id' => (string) $alice->id,
                        'attributes' => [
                            'display_name' => 'Alice',
                            'username' => $alice->username,
                            'avatar' => null,
                        ],
                    ],
                ],
            ]);
    }

    public function testNonCreatorCannotListShares(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/share")
            ->assertForbidden();
    }

    public function testSyncAttachesUpdatesAndDetachesInOneCall(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id => ['can_edit' => false]]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id, 'meta' => ['can_edit' => true]],
                ['type' => 'users', 'id' => (string) $bob->id],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(2, 'included');

        // Absent meta means the use-only grant on the wire too.
        $canEditById = collect($response->json('data'))->pluck('meta.can_edit', 'id');
        self::assertTrue($canEditById[(string) $alice->id]);
        self::assertFalse($canEditById[(string) $bob->id]);

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
            'can_edit' => true,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
            'can_edit' => false,
        ]);
    }

    public function testSyncWithEmptyListDetachesEveryone(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(0, 'included');

        self::assertSame(0, $assistant->sharedUsers()->count());
    }

    public function testSyncDetachesAbsentUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id, $bob->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => (string) $bob->id]],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testNonCreatorCannotSyncShares(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => (string) User::factory()->create()->id]],
        ])->assertForbidden();
    }

    public function testGuestCannotSyncShares(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        // The FormRequest gate answers before auth middleware: 403, not 401.
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => '1']],
        ])->assertForbidden();
    }

    /**
     * The relationship document expects data as an array of identifiers; a
     * single object is a validation error.
     */
    public function testSyncRequiresDataArray(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => ['type' => 'users', 'id' => '1'],
        ])->assertStatus(422);
    }

    public function testSyncRejectsCreatorAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => (string) $owner->id]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
        ]);
    }

    public function testSyncRejectsRemovedUserAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $removed = User::factory()->create(['isRemoved' => true]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => (string) $removed->id]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsAppAccountAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $app = User::factory()->create(['employeetype' => 'app']);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [['type' => 'users', 'id' => (string) $app->id]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsDuplicateUserIds(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id, 'meta' => ['can_edit' => false]],
                ['type' => 'users', 'id' => (string) $alice->id, 'meta' => ['can_edit' => true]],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
    }

    /**
     * The share list is pivot truth: it must list strangers too. The real
     * KnownUsersAccessScope cannot be armed in the test harness (it needs the
     * booted system-context middleware), so this simulates its effect — a
     * global scope hiding everyone but self and the HAWKI user — and expects
     * the sharee to be listed anyway.
     */
    public function testListSharesIgnoresUserAccessScopes(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $stranger = User::factory()->create(['name' => 'Stranger']);
        $assistant->sharedUsers()->sync([$stranger->id]);

        // Global scopes cannot be deregistered on a model, so the simulated
        // scope disarms itself in the finally block instead.
        $state = new \ArrayObject(['armed' => true]);
        User::addGlobalScope('test_access', static function (Builder $builder) use ($state): void {
            if (! $state['armed']) {
                return;
            }
            $builder->where(static function (Builder $q): void {
                $q->where('users.id', 1)->orWhere('users.id', PHP_INT_MAX);
            });
        });

        try {
            $this->actingAsUser($owner);

            $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/share")
                ->assertOk()
                ->assertJson([
                    'data' => [
                        [
                            'type' => 'users',
                            'id' => (string) $stranger->id,
                            'meta' => ['can_edit' => false],
                        ],
                    ],
                    'included' => [
                        [
                            'type' => 'users',
                            'id' => (string) $stranger->id,
                            'attributes' => [
                                'display_name' => 'Stranger',
                                'username' => $stranger->username,
                                'avatar' => null,
                            ],
                        ],
                    ],
                ]);
        } finally {
            $state['armed'] = false;
        }
    }

    public function testSyncIsIdempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAsUser($owner);

        $payload = ['data' => [
            ['type' => 'users', 'id' => (string) $alice->id, 'meta' => ['can_edit' => true]],
            ['type' => 'users', 'id' => (string) $bob->id, 'meta' => ['can_edit' => false]],
        ]];

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // The very same list again: sync replaces, it never compounds —
        // one row per user, grants unchanged.
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        self::assertSame(2, $assistant->sharedUsers()->count());
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
            'can_edit' => true,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
            'can_edit' => false,
        ]);
    }

    // --- write-path regressions: shares are action-writable only ---

    /**
     * The resource update hydrates no shares: an editor may rename, but a
     * `shared_users` relationship in the payload must not rewrite the list.
     */
    public function testEditorCannotRewriteSharesViaResourceUpdate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $editor = User::factory()->create();
        $alice = User::factory()->create();
        $assistant->sharedUsers()->sync([
            $editor->id => ['can_edit' => true],
            $alice->id,
        ]);

        $this->actingAsUser($editor);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Renamed by sharee'],
                'relationships' => [
                    'shared_users' => ['data' => []],
                ],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Renamed by sharee',
        ]);
        self::assertSame(2, $assistant->sharedUsers()->count());
    }

    /**
     * The resource create hydrates no shares: a `shared_users` relationship
     * in the payload is either rejected at the document layer (404, when the
     * identifier does not resolve) or ignored (201) — never a pivot row.
     */
    public function testCreateRejectsSharedUsersRelationship(): void
    {
        Event::fake(AssistantCreatedEvent::class);

        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($owner);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistants', [
            'data' => [
                'type' => 'assistants',
                'relationships' => [
                    'shared_users' => ['data' => [['type' => 'users', 'id' => (string) $alice->id]]],
                ],
            ],
        ]);

        self::assertContains($response->status(), [201, 404]);

        $this->assertDatabaseMissing('assistant_shared_users', ['user_id' => $alice->id]);
    }

    // --- edit grant policy tier ---

    public function testShareeWithEditGrantCanUpdate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $editor = User::factory()->create();
        $assistant->sharedUsers()->sync([$editor->id => ['can_edit' => true]]);

        $this->actingAsUser($editor);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Renamed by sharee'],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Renamed by sharee',
        ]);
    }

    public function testShareeSeesAllowedUpdateLinkOnCollection(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $editor = User::factory()->create();
        $assistant->sharedUsers()->sync([$editor->id => ['can_edit' => true]]);

        $this->actingAsUser($editor);

        // Exercises the eager-loaded pivot path (AssistantSchema::with()).
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonPath('data.0.links.update.meta.message', 'ALLOWED');
    }

    public function testShareeWithoutEditGrantCannotUpdate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id => ['can_edit' => false]]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Nope'],
            ],
        ])->assertForbidden();

        $this->assertDatabaseMissing('assistants', [
            'id' => $assistant->id,
            'name' => 'Nope',
        ]);
    }

    public function testShareeWithEditGrantCannotReleaseOrDelete(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $editor = User::factory()->create();
        $assistant->sharedUsers()->sync([$editor->id => ['can_edit' => true]]);

        $this->actingAsUser($editor);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => ['attributes' => ['release_stage' => 'private']],
        ])->assertForbidden();

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    // --- user search filter ---

    public function testUserSearchMatchesNameUsernameAndEmail(): void
    {
        User::factory()->create(['name' => 'Ada Lovelace', 'username' => 'ada', 'email' => 'ada@example.com']);
        User::factory()->create(['name' => 'Grace Hopper', 'username' => 'grace', 'email' => 'grace@example.com']);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=ada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.username', 'ada');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=hopper')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.username', 'grace');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=grace@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.username', 'grace');
    }

    public function testGuestCannotSearchUsers(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=ada')
            ->assertStatus(401);
    }

    public function testUserSearchExcludesRemovedSystemAndAppAccounts(): void
    {
        $removed = User::factory()->create(['name' => 'Gone Soon', 'isRemoved' => true]);
        $system = User::factory()->create(['name' => 'System Clone', 'employeetype' => 'system']);
        $app = User::factory()->create(['name' => 'Robot One', 'employeetype' => 'app']);

        $this->actingAsUser(User::factory()->create());

        $ids = collect(
            $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=one')
                ->assertOk()
                ->json('data'),
        )->pluck('id')->map(static fn (string $id): int => (int) $id);

        self::assertNotContains($removed->id, $ids);
        self::assertNotContains($system->id, $ids);
        self::assertNotContains($app->id, $ids);
    }

    public function testUserSearchRequiresMinimumLength(): void
    {
        User::factory()->create(['name' => 'Ada Lovelace', 'username' => 'ada']);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=ad')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=' . urlencode('  ad  '))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testUserSearchTreatsLikeWildcardsLiterally(): void
    {
        User::factory()->create(['name' => 'Ada Lovelace', 'username' => 'ada']);
        User::factory()->create(['name' => '100% Real', 'username' => 'percent_user']);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=' . urlencode('%%%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=___')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=' . urlencode('0% R'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.username', 'percent_user');

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=' . urlencode('t_u'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.username', 'percent_user');
    }

    public function testUserSearchIsCappedEvenWithLargePageSize(): void
    {
        User::factory()->count(8)->sequence(
            static fn ($seq): array => ['name' => "Searchable {$seq->index}", 'username' => "searchable{$seq->index}"],
        )->create();

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', '/api/hawki/v1/users?filter[search]=searchable&page[number]=1&page[size]=50')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    // --- is_creator filter ---

    public function testIsCreatorFilterHidesAssistantsSharedWithMe(): void
    {
        $owner = User::factory()->create();
        $mine = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $other = User::factory()->create();
        $theirs = Assistant::factory()->create([
            'creator_id' => $other->id,
            'release_stage' => 'draft',
        ]);
        $theirs->sharedUsers()->sync([$owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[release_stage]=draft&filter[is_creator]=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $mine->id);
    }
}
