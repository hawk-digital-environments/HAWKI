<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Models;

use App\Http\Controllers\Api\V1\ModelsController;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Covers the model catalogue endpoint GET /api/hawki/v1/models/{format?}: the
 * repository's contextual scopes (active + usage-type rules) rendered through the
 * wire-format formatters.
 */
#[CoversClass(ModelsController::class)]
class ModelsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/hawki/v1/models';

    public function testGuestCannotListModels(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized();
    }

    public function testItServesAnEmptyList(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['object' => 'list', 'data' => []]);
    }

    public function testItListsScopedModelsInDeterministicOrder(): void
    {
        $this->seedModel('gpt-4.1-nano', 'chat');
        $this->seedModel('claude-sonnet', 'chat');
        $this->actingAsUser(User::factory()->create());

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        self::assertSame(['claude-sonnet', 'gpt-4.1-nano'], $ids);

        $entry = $response->json('data.0');
        self::assertSame('model', $entry['object']);
        self::assertSame('OpenAI', $entry['owned_by']);
        self::assertSame('claude-sonnet label', $entry['label']);
        self::assertSame('chat', $entry['model_type']);
        self::assertIsInt($entry['created']);
    }

    public function testItHidesModelsOfOtherUsageTypesAndInactiveModels(): void
    {
        $foreign = $this->seedModel('foreign-model');
        DB::table('ai_model_usage_rules')->where('ai_model_id', $foreign->id)->update([
            'usage_type' => 'some-other-context',
        ]);

        $inactive = $this->seedModel('inactive-model');
        $inactive->forceFill(['active' => false])->save();

        $this->seedModel('visible-model');
        $this->actingAsUser(User::factory()->create());

        $ids = array_column($this->getJson(self::ENDPOINT)->json('data'), 'id');

        self::assertSame(['visible-model'], $ids);
    }

    public function testItServesTheOpenResponsesDialect(): void
    {
        $this->seedModel('gpt-4.1-nano');
        $this->actingAsUser(User::factory()->create());

        $entry = $this->getJson(self::ENDPOINT . '/openResponses')->json('data.0');

        self::assertArrayHasKey('created_at', $entry);
        self::assertArrayNotHasKey('created', $entry);
    }

    public function testItRejectsUnknownFormats(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->getJson(self::ENDPOINT . '/definitely-not-a-format')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'unknown_format')
            ->assertJsonPath('error.param', 'format');
    }

    private function seedModel(string $modelId, string $modelType = 'chat'): AiModel
    {
        $provider = AiProvider::firstOrNew(['provider_id' => 'openAi']);
        $provider->fill(['name' => 'OpenAI', 'active' => true, 'adapter_key' => 'openai', 'api_key' => 'test-key']);
        $provider->save();

        $model = AiModel::create([
            'model_id' => $modelId,
            'label' => $modelId . ' label',
            'provider_id' => $provider->id,
            'active' => true,
            'model_type' => $modelType,
            'parameters' => AiModelParameters::fromArray([]),
            'settings' => AiModelSettings::fromArray([]),
            'flags' => AiModelFlags::fromArray([]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
            'limits' => ChatAiModelLimits::fromArray([]),
            'pricing' => ChatAiModelPricing::fromArray([]),
        ]);

        // The AiModel repository scopes queries by usage rules (whereHas on usageRules
        // for the active usage type), so the seeded model needs a matching rule row.
        DB::table('ai_model_usage_rules')->insert([
            'ai_model_id' => $model->id,
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $model;
    }
}
