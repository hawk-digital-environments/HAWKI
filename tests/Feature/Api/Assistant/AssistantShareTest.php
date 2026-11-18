<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantShareTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerCanAttachSharedUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id],
                ['type' => 'users', 'id' => (string) $bob->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testOwnerCanDetachSharedUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $assistant->sharedUsers()->sync([$alice->id, $bob->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

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

    public function testOwnerCanReplaceSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $bob->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testAttachIsIdempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        self::assertEquals(1, $assistant->sharedUsers()->count());
    }

    public function testNonOwnerCannotAttach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAsUser($intruder);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function testNonOwnerCannotDetach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();
        $assistant->sharedUsers()->sync([$target->id]);

        $this->actingAsUser($intruder);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
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

    public function testGuestCannotAttach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $target = User::factory()->create();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertStatus(401);
    }

    public function testAttachRequiresDataArray(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        // to-many relationship expects data as an array; a single object is a
        // structural error (400), not a validation error.
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => ['type' => 'users', 'id' => '1'],
        ])->assertStatus(400);
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

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $viewer->id]],
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
                        'user_id' => $alice->id,
                        'display_name' => 'Alice',
                        'username' => $alice->username,
                        'can_edit' => true,
                        'avatar' => null,
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

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => [
                'shares' => [
                    ['user_id' => $alice->id, 'can_edit' => true],
                    ['user_id' => $bob->id],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

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
            'data' => ['shares' => []],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        self::assertSame(0, $assistant->sharedUsers()->count());
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
            'data' => ['shares' => [['user_id' => User::factory()->create()->id]]],
        ])->assertForbidden();
    }

    public function testSyncRejectsCreatorAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => ['shares' => [['user_id' => $owner->id]]],
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
            'data' => ['shares' => [['user_id' => $removed->id]]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsAppAccountAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $app = User::factory()->create(['employeetype' => 'app']);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => ['shares' => [['user_id' => $app->id]]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsDuplicateUserIds(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", [
            'data' => ['shares' => [
                ['user_id' => $alice->id, 'can_edit' => false],
                ['user_id' => $alice->id, 'can_edit' => true],
            ]],
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
                            'user_id' => $stranger->id,
                            'display_name' => 'Stranger',
                            'username' => $stranger->username,
                            'can_edit' => false,
                            'avatar' => null,
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

        $payload = ['data' => ['shares' => [
            ['user_id' => $alice->id, 'can_edit' => true],
            ['user_id' => $bob->id, 'can_edit' => false],
        ]]];

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
