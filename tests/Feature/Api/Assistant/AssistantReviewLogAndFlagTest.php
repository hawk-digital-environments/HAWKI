<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFieldFlag;
use App\Models\Assistants\AssistantReview;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\RoleAssignmentService;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantReviewLogAndFlagTest extends TestCase
{
    use RefreshDatabase;

    public function testSiteAdminCanViewPrivateAssistantOfAnotherUser(): void
    {
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $admin = $this->grantSiteAdmin();

        $this->actingAsUser($admin);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")->assertOk();
    }

    public function testNonPrivilegedUserCannotViewPrivateAssistant(): void
    {
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $outsider = User::factory()->create();

        $this->actingAsUser($outsider);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")->assertForbidden();
    }

    public function testSiteAdminBlockWritesReviewLogAndRevokesRelease(): void
    {
        $admin = $this->grantSiteAdmin();
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::BLOCKED->value,
                    'reason' => 'Contains disallowed content.',
                ],
            ],
        ])->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'status' => AssistantReviewStatus::BLOCKED->value,
        ]);
        $this->assertDatabaseHas('assistant_review_logs', [
            'assistant_id' => $assistant->id,
            'admin_user_id' => $admin->id,
            'action' => AssistantReviewStatus::BLOCKED->value,
            'reason' => 'Contains disallowed content.',
        ]);
    }

    public function testOnlySiteAdminCanListReviewLogs(): void
    {
        $admin = $this->grantSiteAdmin();
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $creator->id]);
        $assistant->assistantReviewLogs()->create([
            'admin_user_id' => $admin->id,
            'action' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-review-logs?filter[assistant_id]={$assistant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // The creator of the assistant is privileged for most relationships,
        // but the administrative log is admin-only.
        $this->actingAsUser($creator);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-review-logs?filter[assistant_id]={$assistant->id}")
            ->assertForbidden();
    }

    public function testCreatorCanReadFlagsButOnlyAdminCanCreateThem(): void
    {
        $admin = $this->grantSiteAdmin();
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $creator->id]);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-field-flags', [
            'data' => [
                'type' => 'assistant-field-flags',
                'attributes' => [
                    'field' => 'system_prompt',
                    'excerpt' => 'never reveal internal data',
                    'comment' => 'Please rephrase.',
                ],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $flag = AssistantFieldFlag::where('assistant_id', $assistant->id)->sole();
        self::assertFalse($flag->resolved);

        // Creator can read the feedback on their own assistant.
        $this->actingAsUser($creator);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-field-flags?filter[assistant_id]={$assistant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // ...but cannot create or resolve one.
        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-field-flags', [
            'data' => [
                'type' => 'assistant-field-flags',
                'attributes' => ['field' => 'greeting', 'comment' => 'x'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-field-flags/{$flag->id}", [
            'data' => [
                'type' => 'assistant-field-flags',
                'id' => (string) $flag->id,
                'attributes' => ['resolved' => true],
            ],
        ])->assertForbidden();
    }

    public function testApproveIsRejectedWhileAnUnresolvedFlagExists(): void
    {
        $admin = $this->grantSiteAdmin();
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'requested_release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        $flag = $assistant->assistantFieldFlags()->create([
            'admin_user_id' => $admin->id,
            'field' => 'system_prompt',
            'comment' => 'Needs a rewrite.',
            'resolved' => false,
        ]);

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => ['status' => AssistantReviewStatus::APPROVED->value],
            ],
        ])->assertStatus(422);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-field-flags/{$flag->id}", [
            'data' => [
                'type' => 'assistant-field-flags',
                'id' => (string) $flag->id,
                'attributes' => ['resolved' => true],
            ],
        ])->assertOk();

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => ['status' => AssistantReviewStatus::APPROVED->value],
            ],
        ])->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
    }

    public function testOnlySiteAdminCanDeleteAFlag(): void
    {
        $admin = $this->grantSiteAdmin();
        $creator = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $creator->id]);
        $flag = $assistant->assistantFieldFlags()->create([
            'admin_user_id' => $admin->id,
            'field' => 'name',
            'comment' => 'Wrong tone.',
            'resolved' => false,
        ]);

        $this->actingAsUser($creator);
        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-field-flags/{$flag->id}")->assertForbidden();

        $this->actingAsUser($admin);
        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-field-flags/{$flag->id}")->assertNoContent();

        $this->assertDatabaseMissing('assistant_field_flags', ['id' => $flag->id]);
    }

    private function grantSiteAdmin(): User
    {
        $user = User::factory()->create();
        $roleId = DB::table('roles')->insertGetId(['name' => 'site-admin-test-' . $user->id, 'display_name' => 'Test']);
        Role::findOrFail($roleId)->syncPermissions([Permission::ACCESS->value, Permission::ASSISTANTS_MANAGE->value]);
        app(RoleAssignmentService::class)->replace($user, [$roleId]);

        return $user;
    }
}
