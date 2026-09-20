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
use Tests\Concerns\ParsesSseStreams;
use Tests\TestCase;

/**
 * Live verification of the Chat Completions wire format (format key `openai`) against
 * a real provider (gpt-4.1-nano), per the implementation handoff §6: the two-turn
 * client-tool loop, the streaming chunk grammar, and generation-param wiring.
 *
 * Skipped by default: talks to a real provider, so it runs only when
 * OPENRESPONSES_COMPLIANCE_TOKEN holds a non-empty OpenAI API key (same gate as the
 * Open Responses compliance suite).
 */
#[CoversClass(ChatController::class)]
class ChatCompletionsLiveTest extends TestCase
{
    use ParsesSseStreams;
    use RefreshDatabase;
    private const string ENDPOINT = '/api/hawki/v1/chat/openai';
    private const string MODEL = 'gpt-4.1-nano';

    protected function setUp(): void
    {
        parent::setUp();

        if (blank(self::liveToken())) {
            self::markTestSkipped('Live Chat Completions suite is opt-in: set OPENRESPONSES_COMPLIANCE_TOKEN to an OpenAI API key to run it.');
        }

        $this->seedProxyInfrastructure();
    }

    public function testItServesNonStreamingResponses(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'Answer with exactly one word.'],
                ['role' => 'user', 'content' => 'What is the capital of France?'],
            ],
        ]);

        $response->assertOk();
        $body = $response->json();

        self::assertSame('chat.completion', $body['object']);
        self::assertMatchesRegularExpression('/^chatcmpl-/', $body['id']);
        self::assertStringContainsString('gpt-4.1-nano', (string) $body['model']);
        self::assertSame('stop', $body['choices'][0]['finish_reason']);
        self::assertSame('assistant', $body['choices'][0]['message']['role']);
        self::assertNotSame('', trim((string) $body['choices'][0]['message']['content']));
        self::assertGreaterThan(0, $body['usage']['prompt_tokens']);
        self::assertGreaterThan(0, $body['usage']['total_tokens']);
    }

    public function testItStreamsTheFullChunkGrammar(): void
    {
        [$response, $body] = $this->performStreamingRequest([
            'model' => self::MODEL,
            'messages' => [['role' => 'user', 'content' => 'Count from one to three.']],
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ]);

        $response->assertOk();
        self::assertStringNotContainsString('event:', $body);
        self::assertStringEndsWith("data: [DONE]\n\n", $body);

        $chunks = array_map(static fn (array $frame): mixed => $frame['data'], $this->parseDataFrames($body));
        $last = array_key_last($chunks);
        self::assertSame('[DONE]', $chunks[$last]);

        $firstDelta = $chunks[0]['choices'][0]['delta'];
        self::assertSame('assistant', $firstDelta['role']);
        self::assertArrayHasKey('content', $firstDelta);

        $contentChunks = array_filter(
            $chunks,
            static fn (mixed $chunk): bool => \is_array($chunk)
                && isset($chunk['choices'][0]['delta']['content'])
                && '' !== $chunk['choices'][0]['delta']['content'],
        );
        self::assertNotEmpty($contentChunks, 'Expected at least one content delta chunk.');

        $finishChunk = $chunks[$last - 2];
        self::assertSame([], $finishChunk['choices'][0]['delta']);
        self::assertSame('stop', $finishChunk['choices'][0]['finish_reason']);

        $usageChunk = $chunks[$last - 1];
        self::assertSame([], $usageChunk['choices']);
        self::assertGreaterThan(0, $usageChunk['usage']['prompt_tokens']);
    }

    public function testItRunsTheClientToolLoopAcrossTurns(): void
    {
        // Turn 1: the model must hand the declared client tool back as tool_calls.
        $first = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            'messages' => [['role' => 'user', 'content' => 'What is the weather in Berlin? Use the get_weather tool.']],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Gets the current weather for a city.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['city' => ['type' => 'string']],
                        'required' => ['city'],
                    ],
                ],
            ]],
            'tool_choice' => 'auto',
        ]);

        $first->assertOk();
        $firstBody = $first->json();
        self::assertSame('tool_calls', $firstBody['choices'][0]['finish_reason']);

        $toolCalls = $firstBody['choices'][0]['message']['tool_calls'];
        self::assertNotEmpty($toolCalls);
        self::assertSame('get_weather', $toolCalls[0]['function']['name']);

        $arguments = json_decode((string) $toolCalls[0]['function']['arguments'], true);
        self::assertIsArray($arguments);
        self::assertArrayHasKey('city', $arguments);

        // Turn 2: the client executed locally and returns the result as a tool message.
        $second = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'user', 'content' => 'What is the weather in Berlin? Use the get_weather tool.'],
                ['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls],
                ['role' => 'tool', 'tool_call_id' => $toolCalls[0]['id'], 'content' => '{"temperature_c": 21, "condition": "sunny"}'],
            ],
        ]);

        $second->assertOk();
        $secondBody = $second->json();
        self::assertSame('stop', $secondBody['choices'][0]['finish_reason']);

        $answer = mb_strtolower((string) $secondBody['choices'][0]['message']['content']);
        self::assertTrue(
            str_contains($answer, '21') || str_contains($answer, 'sunny'),
            'Expected the final answer to reflect the tool result, got: ' . $answer,
        );
    }

    public function testItHonoursGenerationParams(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            'messages' => [['role' => 'user', 'content' => 'Tell me a very long story about a dragon.']],
            // OpenAI enforces a floor of 16 on the token budget.
            'max_tokens' => 16,
        ]);

        $response->assertOk();
        self::assertSame('length', $response->json('choices.0.finish_reason'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: \Illuminate\Testing\TestResponse, 1: string}
     */
    private function performStreamingRequest(array $data): array
    {
        $captured = '';
        ob_start(static function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response = $this->postJson(self::ENDPOINT, $data);
        ob_end_clean();

        if ('' === $captured) {
            ob_start(static function (string $buffer) use (&$captured): string {
                $captured .= $buffer;

                return '';
            });
            $response->baseResponse->sendContent();
            ob_end_clean();
        }

        return [$response, $captured];
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

        $model = AiModel::create([
            'model_id' => self::MODEL,
            'label' => 'Live Chat Completions Model',
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
            'flags' => AiModelFlags::fromArray(['feature-sampling-parameters']),
            'native_capabilities' => NativeAiModelCapabilities::fromArray(['tool_calling']),
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

        $this->actingAsUser(User::factory()->create());
    }
}
