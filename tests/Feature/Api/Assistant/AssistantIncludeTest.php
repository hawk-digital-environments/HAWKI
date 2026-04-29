<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\Feedback;
use App\Models\Assistants\Review;
use App\Models\Assistants\Tag;
use App\Models\Assistants\UserPrompt;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class AssistantIncludeTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    #[DataProvider('includes')]
    public function test_include_returns_related_resource(string $include, string $type): void
    {
        [$assistant, $owner] = $this->fullySeededAssistant();

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include={$include}")
            ->assertOk();

        $types = collect($response->json('included') ?? [])->pluck('type');

        $this->assertContains(
            $type,
            $types->all(),
            "include={$include} did not surface a \"{$type}\" resource",
        );
    }

    public static function includes(): array
    {
        return [
            'category' => ['category', 'assistant-categories'],
            'assistant_avatar' => ['assistant_avatar', 'assistant-avatars'],
            'setting_values' => ['assistant_setting_values', 'assistant-setting-values'],
            'user_prompts' => ['assistant_user_prompts', 'assistant-user-prompts'],
            'ai_tools' => ['ai_tools', 'ai-tools'],
            'tags' => ['assistant_tags', 'assistant-tags'],
            'creator' => ['creator', 'users'],
            'remix_creator' => ['remix_creator', 'users'],
            'remixed_assistant' => ['remixed_assistant', 'assistants'],
            'versions' => ['versions', 'versions'],
            'organization' => ['organization', 'organizations'],
            'review' => ['assistant_review', 'assistant-reviews'],
            'feedback' => ['assistant_feedback', 'assistant-feedback'],
            'shared_users' => ['shared_users', 'users'],
        ];
    }

    /**
     * @return array{0: Assistant, 1: User}
     */
    private function fullySeededAssistant(): array
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $remixedFrom = Assistant::factory()->create([
            'creator_id' => User::factory()->create()->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'remixed_assistant_id' => $remixedFrom->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        $setting = AssistantSetting::factory()->create();
        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        /** @var UserPrompt $prompt */
        $prompt = $assistant->user_prompts()->create(['text' => 'hello']);
        unset($prompt);

        $tag = Tag::create(['text' => 'sample']);
        $assistant->tags()->attach($tag);
        Feedback::create([
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'nice',
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);
        $assistant->ai_tools()->sync([$this->createAiTool()->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        return [$assistant, $owner];
    }
}
