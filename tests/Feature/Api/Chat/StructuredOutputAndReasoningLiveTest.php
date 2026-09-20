<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat;

use App\Http\Controllers\Api\V1\ChatController;
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
 * Live verification of N6 — structured output and reasoning replay — per the
 * implementation plan: the json_schema channel through the SDK's HasStructuredOutput
 * contract, json_object emulation, and the reasoning-replay loop with a reasoning
 * model (encrypted reasoning state returned, resent, and accepted by the provider).
 *
 * Skipped by default: talks to a real provider, so it runs only when
 * OPENRESPONSES_COMPLIANCE_TOKEN holds a non-empty OpenAI API key (same gate as the
 * compliance suite).
 */
#[CoversClass(ChatController::class)]
class StructuredOutputAndReasoningLiveTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/hawki/v1/chat';
    private const string TEXT_MODEL = 'gpt-4.1-nano';
    private const string REASONING_MODEL = 'o4-mini';

    protected function setUp(): void
    {
        parent::setUp();

        if (blank(self::liveToken())) {
            self::markTestSkipped('Live structured-output/reasoning suite is opt-in: set OPENRESPONSES_COMPLIANCE_TOKEN to an OpenAI API key to run it.');
        }

        $this->seedProxyInfrastructure();
    }

    public function testItEnforcesJsonSchemaResponses(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'model' => self::TEXT_MODEL,
            'input' => 'Describe Alice: a 30 year old engineer from Berlin.',
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'person',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                        'city' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'age'],
                ],
            ]],
        ]);

        $response->assertOk();
        $text = $this->outputText($response->json());

        $decoded = json_decode($text, true);
        self::assertIsArray($decoded, 'Expected schema-constrained JSON output, got: ' . $text);
        self::assertArrayHasKey('name', $decoded);
        self::assertIsInt($decoded['age']);
    }

    public function testItRejectsStreamingJsonSchemaRequests(): void
    {
        // The underlying SDK cannot stream structured output; the proxy fails fast
        // with a spec-shaped 400 instead of a mid-request 500.
        $this->postJson(self::ENDPOINT, [
            'model' => self::TEXT_MODEL,
            'input' => 'Give me a two-person team as JSON.',
            'stream' => true,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'team',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'members' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ]],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'unsupported_response_format');
    }

    public function testItServesJsonObjectResponsesInTheChatCompletionsDialect(): void
    {
        $response = $this->postJson(self::ENDPOINT . '/openai', [
            'model' => self::TEXT_MODEL,
            'messages' => [['role' => 'user', 'content' => 'Give me a person: Alice, 30, Berlin.']],
            'response_format' => ['type' => 'json_object'],
        ]);

        $response->assertOk();
        $decoded = json_decode((string) $response->json('choices.0.message.content'), true);

        self::assertIsArray($decoded, 'Expected a JSON object, got: ' . $response->json('choices.0.message.content'));
    }

    public function testItReplaysReasoningStateAcrossToolCalls(): void
    {
        $tools = [[
            'type' => 'function',
            'name' => 'get_weather',
            'description' => 'Gets the current weather for a city.',
            'parameters' => [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string']],
                'required' => ['city'],
            ],
        ]];

        // Turn 1: the reasoning model must hand the tool back together with its
        // encrypted reasoning state.
        $first = $this->postJson(self::ENDPOINT, [
            'model' => self::REASONING_MODEL,
            'input' => 'What is the weather in Berlin? Use the get_weather tool.',
            'tools' => $tools,
        ]);

        $first->assertOk();
        $firstOutput = $first->json('output');

        $reasoningItem = null;
        $functionCall = null;

        foreach ($firstOutput as $item) {
            if ('reasoning' === ($item['type'] ?? null)) {
                $reasoningItem = $item;
            }

            if ('function_call' === ($item['type'] ?? null)) {
                $functionCall = $item;
            }
        }

        self::assertNotNull($functionCall, 'Expected a function_call output item.');
        self::assertNotNull($reasoningItem, 'Expected a reasoning output item carrying replay state.');
        self::assertNotEmpty($reasoningItem['encrypted_content'] ?? null, 'Expected encrypted reasoning content for replay.');
        self::assertNotEmpty($reasoningItem['id'] ?? null);

        // Turn 2: replay the exact items (reasoning + function_call + output).
        $second = $this->postJson(self::ENDPOINT, [
            'model' => self::REASONING_MODEL,
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'What is the weather in Berlin? Use the get_weather tool.'],
                $reasoningItem,
                $functionCall,
                ['type' => 'function_call_output', 'call_id' => $functionCall['call_id'], 'output' => '{"temperature_c": 21, "condition": "sunny"}'],
            ],
            'tools' => $tools,
        ]);

        $second->assertOk();
        $answer = strtolower($this->outputText($this->completedResponse($second->json())));

        self::assertTrue(
            str_contains($answer, '21') || str_contains($answer, 'sunny'),
            'Expected the final answer to reflect the tool result, got: ' . $answer,
        );
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function completedResponse(array|string $response): array
    {
        if (\is_string($response)) {
            $response = json_decode($response, true) ?? [];
        }

        return $response['response'] ?? $response;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function outputText(array|string $resource): string
    {
        if (\is_string($resource)) {
            return $resource;
        }

        foreach ($resource['output'] ?? [] as $item) {
            if ('message' === ($item['type'] ?? null)) {
                foreach ($item['content'] ?? [] as $part) {
                    if ('output_text' === ($part['type'] ?? null)) {
                        return (string) ($part['text'] ?? '');
                    }
                }
            }
        }

        return '';
    }

    /**
     * env() only sees $_ENV/$_SERVER, which misses process-supplied variables under
     * the default variables_order — hence the getenv() fallback.
     */
    private static function liveToken(): ?string
    {
        $token = env('OPENRESPONSES_COMPLIANCE_TOKEN');

        if (\is_string($token) && '' !== $token) {
            return $token;
        }

        $processToken = getenv('OPENRESPONSES_COMPLIANCE_TOKEN');

        return \is_string($processToken) && '' !== $processToken ? $processToken : null;
    }

    private function seedProxyInfrastructure(): void
    {
        $provider = AiProvider::create([
            'provider_id' => 'openAi',
            'name' => 'OpenAi',
            'active' => true,
            'adapter_key' => 'openai',
            'api_key' => self::liveToken(),
        ]);

        $this->seedModel($provider, self::TEXT_MODEL, samplingParameters: true);
        $this->seedModel($provider, self::REASONING_MODEL, samplingParameters: false);

        $this->actingAsUser(User::factory()->create());
    }

    private function seedModel(AiProvider $provider, string $modelId, bool $samplingParameters): void
    {
        $model = AiModel::create([
            'model_id' => $modelId,
            'label' => $modelId,
            'provider_id' => $provider->id,
            'active' => true,
            'model_type' => 'chat',
            'parameters' => AiModelParameters::fromArray([
                'temperature' => 1,
                'top_p' => 1,
                'max_tokens' => 4096,
                'max_thinking_tokens' => 2048,
            ]),
            'settings' => AiModelSettings::fromArray([
                'file_upload' => true,
                'tool_calling' => true,
                'native_capabilities' => true,
                'max_tool_calling_rounds' => 5,
                'max_tool_calling_rounds_streaming' => 3,
            ]),
            'flags' => AiModelFlags::fromArray($samplingParameters ? ['feature-sampling-parameters'] : []),
            'native_capabilities' => NativeAiModelCapabilities::fromArray(['tool_calling']),
            'limits' => ChatAiModelLimits::fromArray([]),
            'pricing' => ChatAiModelPricing::fromArray([]),
        ]);

        DB::table('ai_model_usage_rules')->insert([
            'ai_model_id' => $model->id,
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
