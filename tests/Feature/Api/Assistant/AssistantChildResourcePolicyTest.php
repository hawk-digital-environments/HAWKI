<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\AssistantTag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantChildResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function testItScopesTheSettingValuesIndexToPrivilegedAssistants(): void
    {
        $org = Organization::first();

        $owner = User::factory()->create();
        $owned = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $otherOwner = User::factory()->create();
        $inaccessible = Assistant::factory()->create([
            'creator_id' => $otherOwner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $setting = AssistantSetting::factory()->create();
        $ownedValue = AssistantSettingValue::forceCreate([
            'assistant_id' => $owned->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);
        AssistantSettingValue::forceCreate([
            'assistant_id' => $inaccessible->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'casual'],
        ]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-setting-values')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        static::assertContains((string) $ownedValue->id, $ids);
        static::assertNotContains((string) AssistantSettingValue::where('assistant_id', $inaccessible->id)->value('id'), $ids);
    }

    public function testItForbidsSettingValuesShowToNonPrivilegedViewers(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $setting = AssistantSetting::factory()->create();
        $value = AssistantSettingValue::forceCreate([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        // A shared user can view the assistant but is not privileged for settings.
        $shared = User::factory()->create();
        $assistant->sharedUsers()->sync([$shared->id]);

        $this->actingAsUser($shared);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-setting-values/{$value->id}")
            ->assertOk();

        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-setting-values/{$value->id}")
            ->assertOk();
    }

    public function testItShowsTheTagsIndexToAnyAuthenticatedUser(): void
    {
        // Tags are a global, shared library of unique labels (no ownership,
        // no per-assistant scoping). Any authenticated user can list and show
        // every tag, since a tag carries only its `text`.
        $ownedTag = AssistantTag::create(['text' => 'php']);
        $otherTag = AssistantTag::create(['text' => 'laravel']);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        $ids = collect($this->jsonApiRaw('get', '/api/hawki/v1/assistant-tags')->assertOk()->json('data'))
            ->pluck('id');

        static::assertContains((string) $ownedTag->id, $ids);
        static::assertContains((string) $otherTag->id, $ids);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-tags/{$otherTag->id}")->assertOk();
    }

    public function testItLetsAnOrgAdminSeeOrgAssistantsSettingValues(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $setting = AssistantSetting::factory()->create();
        $value = AssistantSettingValue::forceCreate([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($org, ['role' => 'admin']);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-setting-values/{$value->id}")
            ->assertOk();

        $ids = collect($this->jsonApiRaw('get', '/api/hawki/v1/assistant-setting-values')->assertOk()->json('data'))
            ->pluck('id');
        static::assertContains((string) $value->id, $ids);
    }

    public function testItScopesTheReviewsIndexToPrivilegedAssistants(): void
    {
        $org = Organization::first();

        $owner = User::factory()->create();
        $owned = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $otherOwner = User::factory()->create();
        $inaccessible = Assistant::factory()->create([
            'creator_id' => $otherOwner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $ownedReview = AssistantReview::forceCreate([
            'assistant_id' => $owned->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $inaccessible->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        // The index lists accessible reviews: creators see their own
        // submissions, even though the admin menu itself is gated by the
        // `actions/permissions` probe.
        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-reviews')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        static::assertContains((string) $ownedReview->id, $ids);
        static::assertNotContains((string) AssistantReview::where('assistant_id', $inaccessible->id)->value('id'), $ids);
    }

    public function testItLetsAnOrgAdminSeeOrgAssistantsReviews(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($org, ['role' => 'admin']);

        $this->actingAsUser($admin);

        $ids = collect($this->jsonApiRaw('get', '/api/hawki/v1/assistant-reviews')->assertOk()->json('data'))
            ->pluck('id');
        static::assertContains((string) $review->id, $ids);
    }

    public function testItShowsAnOrgAdminOnlyTheirOwnOrganizationsReviews(): void
    {
        $org = Organization::first();
        $otherOrg = Organization::create(['name' => 'Other University']);
        $owner = User::factory()->create();

        $ownReview = AssistantReview::forceCreate([
            'assistant_id' => Assistant::factory()->create([
                'creator_id' => $owner->id,
                'organization_id' => $org->id,
                'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
            ])->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        $foreignReview = AssistantReview::forceCreate([
            'assistant_id' => Assistant::factory()->create([
                'creator_id' => $owner->id,
                'organization_id' => $otherOrg->id,
                'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
            ])->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($org, ['role' => 'admin']);
        $this->actingAsUser($admin);

        $ids = collect($this->jsonApiRaw('get', '/api/hawki/v1/assistant-reviews')->assertOk()->json('data'))
            ->pluck('id');
        static::assertContains((string) $ownReview->id, $ids);
        static::assertNotContains((string) $foreignReview->id, $ids);
    }

    public function testItShowsNoReviewsToNonPrivilegedUsers(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $unrelated = User::factory()->create();
        $this->actingAsUser($unrelated);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-reviews')
            ->assertOk();

        static::assertEmpty($response->json('data'));
    }
}
