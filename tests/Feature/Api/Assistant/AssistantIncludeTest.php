<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\Assistants\AssistantFeedback;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\AssistantTag;
use App\Models\Assistants\AssistantUserPrompt;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

#[CoversNothing()]
class AssistantIncludeTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    #[DataProvider('includes')]
    public function testIncludeReturnsRelatedResource(string $include, string $type): void
    {
        [$assistant, $owner] = $this->fullySeededAssistant();

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include={$include}")
            ->assertOk();

        $types = collect($response->json('included') ?? [])->pluck('type');

        self::assertContains(
            $type,
            $types->all(),
            "include={$include} did not surface a \"{$type}\" resource",
        );
    }

    public static function includes(): iterable
    {
        yield from [
            'assistant_category' => ['assistant_category', 'assistant-categories'],
            'assistant_avatar' => ['assistant_avatar', 'assistant-avatars'],
            'setting_values' => ['assistant_setting_values', 'assistant-setting-values'],
            'assistantUserPrompts' => ['assistant_user_prompts', 'assistant-user-prompts'],
            'ai_tools' => ['ai_tools', 'ai-tools'],
            'assistantTags' => ['assistant_tags', 'assistant-tags'],
            'creator' => ['creator', 'users'],
            'remix_creator' => ['remix_creator', 'users'],
            'remixed_assistant' => ['remixed_assistant', 'assistants'],
            'assistant_versions' => ['assistant_versions', 'assistant-versions'],
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
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'remixed_assistant_id' => $remixedFrom->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $setting = AssistantSetting::factory()->create();
        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        /** @var AssistantUserPrompt $prompt */
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'hello']);
        unset($prompt);

        $tag = AssistantTag::create(['text' => 'sample']);
        $assistant->assistantTags()->attach($tag);
        AssistantFeedback::create([
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'nice',
        ]);
        AssistantReview::create([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        $assistant->ai_tools()->sync([$this->createAiTool()->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        return [$assistant, $owner];
    }
}
