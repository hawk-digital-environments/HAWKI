<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Compliance;

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
use Tests\Feature\Api\Compliance\OpenResponsesComplianceTestFixtures\SpecSchemaValidator;
use Tests\TestCase;

/**
 * Open Responses compliance suite, ported from the reference runner
 * (`research/openresponses/bin/compliance-test.ts` + `src/lib/compliance-tests.ts`).
 *
 * Covers the stateless-compatible subset of the published acceptance tests: the proxy
 * deliberately implements no server-side conversation state, so the WebSocket and
 * compaction tests of the official suite are not ported (they would require
 * `store`/`previous_response_id` semantics we reject with 4xx by design).
 *
 * Ported tests: basic-response, assistant-phase, response-output-phase-schema,
 * streaming-response, system-prompt, tool-calling, image-input, multi-turn.
 * The `multi-turn` port verifies the stateless reading of the official test: the full
 * history is sent as input items, without `previous_response_id`.
 *
 * Skipped by default: the suite talks to a real provider, so it runs only when
 * OPENRESPONSES_COMPLIANCE_TOKEN holds a non-empty API key (set it to an OpenAI key).
 */
#[CoversClass(ChatController::class)]
class OpenResponsesComplianceTest extends TestCase
{
    use ParsesSseStreams;
    use RefreshDatabase;
    private const string ENDPOINT = '/api/hawki/v1/chat';
    private const string MODEL = 'gpt-4.1-nano';
    private const string RED_SQUARE_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAIAAABMXPacAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABMUlEQVR4nO3RMQ0AIADAMEAIDvBvBjHI6MGqYMnm3WfEWTrgdw3AGoA1AGsA1gCsAVgDsAZgDcAagDUAawDWAKwBWAOwBmANwBqANQBrANYArAFYA7AGYA3AGoA1AGsA1gCsAVgDsAZgDcAagDUAawDWAKwBWAOwBmANwBqANQBrANYArAFYA7AGYA3AGoA1AGsA1gCsAVgDsAZgDcAagDUAawDWAKwBWAOwBmANwBqANQBrANYArAFYA7AGYA3AGoA1AGsA1gCsAVgDsAZgDcAagDUAawDWAKwBWAOwBmANwBqANQBrANYArAFYA7AGYA3AGoA1AGsA1gCsAVgDsAZgDcAagDUAawDWAKwBWAOwBmANwBqANQBrANYArAFYA7AGYA3AGoA1AGsA1gCsAdgD5QMCJ4pu+vMAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        if (blank(self::complianceToken())) {
            self::markTestSkipped('Open Responses compliance suite is opt-in: set OPENRESPONSES_COMPLIANCE_TOKEN to an OpenAI API key to run it.');
        }

        $this->seedProxyInfrastructure();
    }

    public function testItPassesBasicResponse(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Say hello in exactly 3 words.'],
            ],
        ]);

        $this->assertValidCompletedResponse($response);
    }

    public function testItPassesAssistantPhase(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                ['type' => 'message', 'role' => 'assistant', 'phase' => 'commentary', 'content' => 'I should answer with the saved number.'],
                ['type' => 'message', 'role' => 'assistant', 'phase' => 'final_answer', 'content' => 'The number is four.'],
                ['type' => 'message', 'role' => 'user', 'content' => 'Repeat only the number.'],
            ],
        ]);

        $this->assertValidCompletedResponse($response);
    }

    public function testItPassesResponseOutputPhaseSchema(): void
    {
        // Local schema fixture of the official suite: no HTTP request. Validates that
        // assistant `phase` labels are representable in the ResponseResource schema.
        $fixture = $this->phaseSchemaFixture();

        $errors = SpecSchemaValidator::validate($fixture, 'ResponseResource');
        self::assertSame([], $errors, 'Phase-labelled fixture must satisfy the ResponseResource schema: ' . implode('; ', $errors));

        self::assertTrue(
            $this->hasAssistantMessagePhase($fixture, 'commentary')
            && $this->hasAssistantMessagePhase($fixture, 'final_answer'),
            'Fixture must carry both assistant phase labels.',
        );
    }

    public function testItPassesStreamingResponse(): void
    {
        [$response, $body] = $this->complianceStreamingRequest([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Count from 1 to 5.'],
            ],
        ]);

        $response->assertStatus(200);
        self::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $events = $this->parseSseEvents($body);
        self::assertNotSame([], $events, 'No streaming events received.');
        self::assertStringEndsWith("data: [DONE]\n\n", $body, 'Stream must terminate with data: [DONE]');

        foreach ($events as $event) {
            self::assertSame(
                [],
                SpecSchemaValidator::validateStreamEvent(\is_array($event['data']) ? $event['data'] : []),
                'Event ' . $event['event'] . ' violates its streaming-event schema: '
                . implode('; ', SpecSchemaValidator::validateStreamEvent(\is_array($event['data']) ? $event['data'] : [])),
            );
        }

        $this->assertMonotonicSequenceNumbers($events);
        $this->assertBalancedItemLifecycle($events);

        $completed = $this->firstEvent($events, 'response.completed');
        self::assertSame([], SpecSchemaValidator::validate($completed['data']['response'], 'ResponseResource'));
        self::assertSame('completed', $completed['data']['response']['status']);
        self::assertNotSame([], $completed['data']['response']['output'], 'Final response has no output items.');
    }

    public function testItPassesSystemPrompt(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                ['type' => 'message', 'role' => 'system', 'content' => 'You are a pirate. Always respond in pirate speak.'],
                ['type' => 'message', 'role' => 'user', 'content' => 'Say hello.'],
            ],
        ]);

        $this->assertValidCompletedResponse($response);
    }

    public function testItPassesToolCalling(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => "What's the weather like in San Francisco?"],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'name' => 'get_weather',
                    'description' => 'Get the current weather for a location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The city and state, e.g. San Francisco, CA',
                            ],
                        ],
                        'required' => ['location'],
                    ],
                ],
            ],
        ]);

        $data = $response->json();

        self::assertSame(
            [],
            SpecSchemaValidator::validate($data, 'ResponseResource'),
            implode('; ', SpecSchemaValidator::validate($data, 'ResponseResource')),
        );
        self::assertSame('completed', $data['status']);
        self::assertTrue(
            collect($data['output'])->contains(static fn (array $item): bool => 'function_call' === ($item['type'] ?? null)),
            'Expected a function_call output item (client tool handoff).',
        );
    }

    /**
     * Deviation from the official fixture: the suite's bundled 32x32 data-URL PNG is
     * rejected by OpenAI's current image validation ("does not represent a valid
     * image") for every model; this port sends a 128x128 PNG with identical intent
     * (image data URL in user content, forwarded byte-identically by the proxy).
     */
    public function testItPassesImageInput(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'What do you see in this image? Answer in one sentence.'],
                        ['type' => 'input_image', 'image_url' => self::RED_SQUARE_PNG_DATA_URL],
                    ],
                ],
            ],
        ]);

        $this->assertValidCompletedResponse($response);
    }

    public function testItPassesMultiTurnConversation(): void
    {
        $response = $this->complianceRequest([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'My name is Alice.'],
                ['type' => 'message', 'role' => 'assistant', 'content' => 'Hello Alice! Nice to meet you. How can I help you today?'],
                ['type' => 'message', 'role' => 'user', 'content' => 'What is my name?'],
            ],
        ]);

        $this->assertValidCompletedResponse($response);
    }

    /**
     * Reads the gating token from the Laravel env layer first and from the raw process
     * environment second — php.ini's variables_order can keep process env vars out of
     * $_ENV/$_SERVER, which is where env() looks.
     */
    private static function complianceToken(): ?string
    {
        $token = env('OPENRESPONSES_COMPLIANCE_TOKEN');

        if (\is_string($token) && '' !== $token) {
            return $token;
        }

        $processToken = getenv('OPENRESPONSES_COMPLIANCE_TOKEN');

        return \is_string($processToken) && '' !== $processToken ? $processToken : null;
    }

    // =========================================================================
    // Infrastructure
    // =========================================================================

    /**
     * Seeds the minimal proxy infrastructure: one active OpenAI provider (key from the
     * compliance token) and the model under test.
     */
    private function seedProxyInfrastructure(): void
    {
        $provider = AiProvider::create([
            'provider_id' => 'openAi',
            'name' => 'OpenAi',
            'active' => true,
            'adapter_key' => 'openai',
            'api_key' => self::complianceToken(),
        ]);

        $model = AiModel::create([
            'model_id' => self::MODEL,
            'label' => 'Compliance Model',
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

    /**
     * @param array<string, mixed> $body
     */
    private function complianceRequest(array $body): \Illuminate\Testing\TestResponse
    {
        $response = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            ...$body,
        ]);

        $response->assertStatus(200);

        return $response;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: \Illuminate\Testing\TestResponse, 1: string}
     */
    private function complianceStreamingRequest(array $body): array
    {
        $response = $this->postJson(self::ENDPOINT, [
            'model' => self::MODEL,
            'stream' => true,
            ...$body,
        ]);

        $response->assertStatus(200);

        return $this->captureStreamedBody($response);
    }

    private function assertValidCompletedResponse(\Illuminate\Testing\TestResponse $response): void
    {
        $data = $response->json();
        $errors = SpecSchemaValidator::validate($data, 'ResponseResource');

        self::assertSame([], $errors, 'ResponseResource violations: ' . implode('; ', $errors));
        self::assertSame('completed', $data['status']);
        self::assertNotSame([], $data['output'], 'Response has no output items.');
    }

    /**
     * @param array<int, array{event: string, data: mixed}> $events
     */
    private function assertMonotonicSequenceNumbers(array $events): void
    {
        $previous = -1;

        foreach ($events as $event) {
            $sequence = \is_array($event['data']) ? ($event['data']['sequence_number'] ?? null) : null;

            self::assertIsInt($sequence, 'Event ' . $event['event'] . ' has no integer sequence_number.');
            self::assertGreaterThan(
                $previous,
                $sequence,
                'sequence_number must increase strictly across events (got ' . $sequence . ' after ' . $previous . ').',
            );
            $previous = $sequence;
        }
    }

    /**
     * Spec ordering rule: streamable item content is framed by
     * output_item.added → content_part.added → deltas → content_part.done → output_item.done,
     * and an item is never left open at the end of the stream.
     *
     * @param array<int, array{event: string, data: mixed}> $events
     */
    private function assertBalancedItemLifecycle(array $events): void
    {
        $openItems = 0;
        $openParts = 0;

        foreach ($events as $event) {
            $openItems += match ($event['event']) {
                'response.output_item.added' => 1,
                'response.output_item.done' => -1,
                default => 0,
            };
            $openParts += match ($event['event']) {
                'response.content_part.added' => 1,
                'response.content_part.done' => -1,
                default => 0,
            };

            self::assertGreaterThanOrEqual(0, $openParts, 'content_part.done emitted before its content_part.added.');
            self::assertGreaterThanOrEqual(0, $openItems, 'output_item.done emitted before its output_item.added.');
        }

        self::assertSame(0, $openItems, 'Every opened output item must be closed before the stream ends.');
        self::assertSame(0, $openParts, 'Every opened content part must be closed before the stream ends.');
    }

    /**
     * @return array<string, mixed>
     */
    private function phaseSchemaFixture(): array
    {
        return [
            'id' => 'resp_phase_schema',
            'object' => 'response',
            'created_at' => 1764967971,
            'completed_at' => 1764967972,
            'status' => 'completed',
            'incomplete_details' => null,
            'model' => self::MODEL,
            'previous_response_id' => null,
            'instructions' => null,
            'output' => [
                [
                    'id' => 'msg_phase_commentary',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'phase' => 'commentary',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'I am checking the answer.', 'annotations' => []],
                    ],
                ],
                [
                    'id' => 'msg_phase_final',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'phase' => 'final_answer',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'The answer is four.', 'annotations' => []],
                    ],
                ],
            ],
            'error' => null,
            'tools' => [],
            'tool_choice' => 'auto',
            'truncation' => 'disabled',
            'parallel_tool_calls' => true,
            'text' => ['format' => ['type' => 'text']],
            'top_p' => 1,
            'presence_penalty' => 0,
            'frequency_penalty' => 0,
            'top_logprobs' => 0,
            'temperature' => 1,
            'reasoning' => ['effort' => null, 'summary' => null],
            'usage' => [
                'input_tokens' => 1,
                'output_tokens' => 2,
                'total_tokens' => 3,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens_details' => ['reasoning_tokens' => 0],
            ],
            'max_output_tokens' => null,
            'max_tool_calls' => null,
            'store' => true,
            'background' => false,
            'service_tier' => 'default',
            'metadata' => [],
            'safety_identifier' => null,
            'prompt_cache_key' => null,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hasAssistantMessagePhase(array $response, string $phase): bool
    {
        foreach ($response['output'] ?? [] as $item) {
            if (\is_array($item)
                && 'message' === ($item['type'] ?? null)
                && 'assistant' === ($item['role'] ?? null)
                && ($item['phase'] ?? null) === $phase) {
                return true;
            }
        }

        return false;
    }
}
