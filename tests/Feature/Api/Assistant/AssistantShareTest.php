<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
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
     * Share writes live on the audience and co-author actions alone: POST/PATCH/DELETE on the
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

    // --- visibility: co-authors always, audience only while exclusive ---

    public function testCoAuthorCanListAndShowPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'private']);

        $coAuthor = User::factory()->create();
        $assistant->sharedUsers()->sync([$coAuthor->id => ['can_edit' => true]]);

        $this->actingAsUser($coAuthor);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk();

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[shared_with_user]=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testCoAuthorCanShowDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'draft']);

        $coAuthor = User::factory()->create();
        $assistant->sharedUsers()->sync([$coAuthor->id => ['can_edit' => true]]);

        $this->actingAsUser($coAuthor);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk();
    }

    /**
     * Until the exclusive stage is approved the assistant waits as private,
     * and its audience must not reach it yet.
     */
    public function testAudienceCannotSeePrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
            'requested_release_stage' => 'exclusive',
        ]);

        $member = User::factory()->create();
        $assistant->sharedUsers()->sync([$member->id => ['can_edit' => false]]);

        $this->actingAsUser($member);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testAudienceSeesExclusiveAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'exclusive']);

        $member = User::factory()->create();
        $assistant->sharedUsers()->sync([$member->id => ['can_edit' => false]]);

        $this->actingAsUser($member);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.links.update.meta.message', 'DENIED');

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[shared_with_user]=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testExclusiveAssistantIsHiddenFromEveryoneElse(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'exclusive']);
        $assistant->sharedUsers()->sync([User::factory()->create()->id => ['can_edit' => false]]);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testRemovingCoAuthorRevokesVisibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'private']);

        $coAuthor = User::factory()->create();
        $assistant->sharedUsers()->sync([$coAuthor->id => ['can_edit' => true]]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors", ['data' => []])
            ->assertSuccessful();

        $this->actingAsUser($coAuthor);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    // --- actions/audience + actions/co-authors: list + sync ---

    public function testCreatorListsEachShareListSeparately(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([
            $alice->id => ['can_edit' => false],
            $bob->id => ['can_edit' => true],
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [['type' => 'users', 'id' => (string) $alice->id]],
                'included' => [[
                    'type' => 'users',
                    'id' => (string) $alice->id,
                    'attributes' => [
                        'display_name' => 'Alice',
                        'username' => $alice->username,
                        'avatar' => null,
                    ],
                ]],
            ]);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $bob->id);
    }

    public function testOnlyTheCreatorManagesShareLists(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'organizational']);

        $coAuthor = User::factory()->create();
        $assistant->sharedUsers()->sync([$coAuthor->id => ['can_edit' => true]]);

        foreach ([$coAuthor, User::factory()->create()] as $user) {
            $this->actingAsUser($user);

            foreach (['audience', 'co-authors'] as $action) {
                $uri = "/api/hawki/v1/assistants/{$assistant->id}/actions/{$action}";
                $this->jsonApiRaw('get', $uri)->assertForbidden();
                $this->jsonApiRaw('post', $uri, [
                    'data' => [['type' => 'users', 'id' => (string) User::factory()->create()->id]],
                ])->assertForbidden();
            }
        }
    }

    public function testGuestCannotSyncShares(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        // The FormRequest gate answers before auth middleware: 403, not 401.
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [['type' => 'users', 'id' => '1']],
        ])->assertForbidden();
    }

    public function testSyncAudienceReplacesTheAudienceOnly(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $assistant->sharedUsers()->sync([
            $alice->id => ['can_edit' => false],
            $bob->id => ['can_edit' => true],
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [['type' => 'users', 'id' => (string) $carol->id]],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $carol->id);

        $this->assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $alice->id]);
        $this->assertDatabaseHas('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $carol->id, 'can_edit' => false]);
        $this->assertDatabaseHas('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $bob->id, 'can_edit' => true]);
    }

    /**
     * A co-author already has every right the audience grants: listing them
     * in the audience neither demotes them nor duplicates the row.
     */
    public function testSyncAudienceSkipsCoAuthors(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$bob->id => ['can_edit' => true]]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [['type' => 'users', 'id' => (string) $bob->id]],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $bob->id, 'can_edit' => true]);
    }

    public function testSyncCoAuthorsPromotesAudienceMembers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id => ['can_edit' => false]]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        self::assertSame(1, $assistant->sharedUsers()->count());
        $this->assertDatabaseHas('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $alice->id, 'can_edit' => true]);
    }

    public function testSyncWithEmptyListDetachesOnlyThatList(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([
            $alice->id => ['can_edit' => false],
            $bob->id => ['can_edit' => true],
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors", ['data' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(0, 'included');

        $this->assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $bob->id]);
        $this->assertDatabaseHas('assistant_shared_users', ['assistant_id' => $assistant->id, 'user_id' => $alice->id]);
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

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => ['type' => 'users', 'id' => '1'],
        ])->assertStatus(422);
    }

    public function testSyncRejectsCreatorAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors", [
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

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [['type' => 'users', 'id' => (string) $removed->id]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsAppAccountAsTarget(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $app = User::factory()->create(['employeetype' => 'app']);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [['type' => 'users', 'id' => (string) $app->id]],
        ])->assertStatus(422);
    }

    public function testSyncRejectsDuplicateUserIds(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id],
                ['type' => 'users', 'id' => (string) $alice->id],
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

            $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/actions/audience")
                ->assertOk()
                ->assertJson([
                    'data' => [['type' => 'users', 'id' => (string) $stranger->id]],
                    'included' => [[
                        'type' => 'users',
                        'id' => (string) $stranger->id,
                        'attributes' => [
                            'display_name' => 'Stranger',
                            'username' => $stranger->username,
                            'avatar' => null,
                        ],
                    ]],
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
            ['type' => 'users', 'id' => (string) $alice->id],
            ['type' => 'users', 'id' => (string) $bob->id],
        ]];

        // The very same list twice: sync replaces, it never compounds.
        foreach ([1, 2] as $_) {
            $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/co-authors", $payload)
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }

        self::assertSame(2, $assistant->sharedUsers()->count());
    }

    // --- exclusive release ---

    public function testExclusiveReleaseNeedsAnAudience(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'private']);

        $this->actingAsUser($owner);

        $this->releaseAs($assistant, 'exclusive')->assertStatus(422);

        self::assertNull($assistant->refresh()->requested_release_stage);
    }

    public function testExclusiveReleaseWaitsForReviewAsPrivate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'draft']);
        $assistant->sharedUsers()->sync([User::factory()->create()->id => ['can_edit' => false]]);

        $this->actingAsUser($owner);

        $this->releaseAs($assistant, 'exclusive')->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        self::assertSame(AssistantReleaseStage::EXCLUSIVE, $assistant->requested_release_stage);
        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }

    public function testApprovalPromotesToExclusive(): void
    {
        $assistant = Assistant::factory()->create([
            'release_stage' => 'private',
            'requested_release_stage' => 'exclusive',
        ]);

        app(AssistantService::class)->promoteRequested($assistant);

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::EXCLUSIVE, $assistant->release_stage);
        self::assertNull($assistant->requested_release_stage);
    }

    /**
     * Exclusive reaches fewer people than the organization: stepping down
     * from organizational needs no fresh review.
     */
    public function testMovingFromOrganizationalToExclusiveIsFree(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'organizational']);
        $assistant->sharedUsers()->sync([User::factory()->create()->id => ['can_edit' => false]]);

        $this->actingAsUser($owner);

        $this->releaseAs($assistant, 'exclusive')->assertOk();

        self::assertSame(AssistantReleaseStage::EXCLUSIVE, $assistant->refresh()->release_stage);
    }

    private function releaseAs(Assistant $assistant, string $stage): TestResponse
    {
        return $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['release_stage' => $stage],
            ],
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

    // --- co-author policy tier ---

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
