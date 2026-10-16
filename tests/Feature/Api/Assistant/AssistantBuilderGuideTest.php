<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Assistant\BuilderGuide\AssistantBuilderGuideService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\OpenAiProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Services\Ai\Agents\ChatAgentAssistantPayloadTransformationTestFixtures\CapturingTextGateway;
use Tests\TestCase;

/**
 * The builder's guide chat endpoint (`POST /assistants/{id}/guide-turns`):
 * authorization, request validation and the structured-output round trip
 * through the real agent pipeline, with the provider boundary faked by a
 * {@see CapturingTextGateway}.
 */
#[CoversClass(AssistantBuilderGuideService::class)]
class AssistantBuilderGuideTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerReceivesReplyAndNormalizedUpdates(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);
        $category = AssistantCategory::query()->create(['text' => 'Teaching']);
        // Taken by another assistant, so the guide's proposal gets a suffix.
        $this->createAssistant(User::factory()->create(), 'statistics-tutor');

        $gateway = new CapturingTextGateway([[
            'reply' => '  I drafted a first version.  ',
            'updates' => [
                'name' => 'Statistics Tutor',
                'handle' => 'Statistics Tutor!',
                'categoryId' => (string) $category->id,
                'model' => 'guide-model',
                'description' => null,
                'detailDescription' => '   ',
                'systemPrompt' => 'You are a patient statistics tutor.',
                'greeting' => null,
                'starterPrompts' => ['What is a p-value?', '', 'What is a p-value?', 'Explain variance'],
            ],
        ]]);
        $this->mockProviderInfrastructure($gateway);

        $response = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'What should your assistant do?'],
                ['role' => 'user', 'content' => 'Tutor first-year students in statistics.'],
            ], ['name' => 'Draft name', 'starterPrompts' => [], 'model' => null]),
        );

        $structured = $this->dataOf($response->assertOk());
        self::assertSame('I drafted a first version.', $structured['reply']);
        self::assertSame([
            'handle' => 'statistics-tutor-2',
            'categoryId' => (string) $category->id,
            'model' => 'guide-model',
            'name' => 'Statistics Tutor',
            'systemPrompt' => 'You are a patient statistics tutor.',
            'starterPrompts' => ['What is a p-value?', 'Explain variance'],
        ], $structured['updates']);

        self::assertCount(1, $gateway->capturedSteps);
        $step = $gateway->capturedSteps[0];
        self::assertStringContainsString('"name": "Draft name"', $step['instructions']);
        self::assertStringContainsString("- {$category->id}: Teaching", $step['instructions']);
        self::assertStringContainsString('- guide-model: Guide Model', $step['instructions']);
        self::assertStringContainsString('Still empty: handle, description, categoryId, systemPrompt, model.', $step['instructions']);
        self::assertCount(3, $step['messages']);
        self::assertSame('Tutor first-year students in statistics.', $step['messages'][2]->content);
    }

    public function testUnknownCategoryAndModelAreDropped(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        $this->mockProviderInfrastructure(new CapturingTextGateway([[
            'reply' => 'Done.',
            'updates' => ['categoryId' => '999999', 'model' => 'not-available', 'handle' => '!!!'],
        ]]));

        $response = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Build it for me']], []),
        );

        $structured = $this->dataOf($response->assertOk());
        self::assertSame('Done.', $structured['reply']);
        self::assertSame([], $structured['updates']);
    }

    public function testSettingsAreFilledFromTheirOptions(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        foreach (['language' => ['', 'en', 'de'], 'formality' => ['', 'casual', 'academic']] as $key => $values) {
            AssistantSetting::query()->create([
                'key' => $key,
                'label' => "assistants.settings.{$key}.label",
                'ui_type' => 'select',
                'ui_options' => array_map(static fn (string $v): array => ['value' => $v, 'label' => $v], $values),
            ]);
        }

        $gateway = new CapturingTextGateway([[
            'reply' => 'Set the tone.',
            // Answer style has no options here, so it is not offered and dropped.
            'updates' => ['language' => 'de', 'formality' => 'pirate', 'answerStyle' => 'detailed'],
        ]]);
        $this->mockProviderInfrastructure($gateway);

        $response = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Answer in German']],
                ['language' => null, 'formality' => 'academic', 'answerStyle' => null]),
        );

        $structured = $this->dataOf($response->assertOk());
        self::assertSame('Set the tone.', $structured['reply']);
        self::assertSame(['language' => 'de'], $structured['updates']);

        $instructions = $gateway->capturedSteps[0]['instructions'];
        self::assertStringContainsString('"formality": "academic"', $instructions);
        self::assertStringContainsString('- language: en, de', $instructions);
        self::assertStringContainsString('- formality: casual, academic', $instructions);
        self::assertStringNotContainsString('- answerStyle:', $instructions);
    }

    public function testTagsTakeTheExistingSpellingAndAreCapped(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);
        AssistantTag::query()->create(['text' => 'Statistics']);

        $gateway = new CapturingTextGateway([[
            'reply' => 'Tagged it.',
            'updates' => ['tags' => ['statistics', ' Tutoring ', 'STATISTICS', '', str_repeat('x', 41), 'Maths', 'R', 'Exams', 'Surplus']],
        ]]);
        $this->mockProviderInfrastructure($gateway);

        $response = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Add tags']], ['tags' => ['Maths']]),
        );

        $structured = $this->dataOf($response->assertOk());
        self::assertSame('Tagged it.', $structured['reply']);
        self::assertSame(['tags' => ['Statistics', 'Tutoring', 'Maths', 'R', 'Exams']], $structured['updates']);

        $instructions = $gateway->capturedSteps[0]['instructions'];
        self::assertStringContainsString('"Maths"', $instructions);
        // The guide only proposes tags; creating them is the frontend's job.
        self::assertSame(1, AssistantTag::query()->count());
    }

    public function testAvatarKeepsOnlyAnEmojiAndAnOfferedBackground(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        $gateway = new CapturingTextGateway([
            ['reply' => 'New look.', 'updates' => ['avatar' => ['emoji' => ' 📊 ', 'background' => 'midnight']]],
            ['reply' => 'New look.', 'updates' => ['avatar' => ['emoji' => 'Chart', 'background' => 'neon-pink']]],
        ]);
        $this->mockProviderInfrastructure($gateway);

        $first = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Suggest an avatar']],
                ['avatar' => ['emoji' => '🤖', 'background' => 'aurora']], ['aurora', 'midnight']),
        )->assertOk();
        $structured = $this->dataOf($first);
        self::assertSame('New look.', $structured['reply']);
        self::assertSame(['avatar' => ['emoji' => '📊', 'background' => 'midnight']], $structured['updates']);

        // Plain text is no emoji, and an unknown background is no preset.
        $second = $this->dataOf($this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Suggest an avatar']],
                ['avatar' => ['emoji' => '🤖', 'background' => 'aurora']], ['aurora', 'midnight']),
        )->assertOk());
        self::assertSame([], $second['updates']);

        $instructions = $gateway->capturedSteps[0]['instructions'];
        self::assertStringContainsString('background ids that suits it: aurora, midnight.', $instructions);
        self::assertStringContainsString('"emoji": "🤖"', $instructions);
    }

    public function testAReplyWithoutUpdatesStillSendsAnObject(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        $this->mockProviderInfrastructure(new CapturingTextGateway([[
            'reply' => 'I run on the configured guide model.',
            'updates' => ['name' => null, 'handle' => null, 'categoryId' => null, 'model' => null],
        ]]));

        $response = $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Which model do you use?']], []),
        )->assertOk();

        // The client validates `updates` as an object; PHP encodes an empty array as `[]`.
        self::assertStringContainsString('"updates":{}', $response->getContent());
    }

    public function testNonOwnerIsForbidden(): void
    {
        $assistant = $this->createAssistant(User::factory()->create());
        $this->actingAsUser(User::factory()->create());

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'user', 'content' => 'Hi']], []),
        )->assertForbidden();
    }

    public function testLastMessageMustComeFromTheUser(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/guide-turns",
            $this->request([['role' => 'assistant', 'content' => 'Hi']], []),
        )->assertUnprocessable();
    }

    private function createAssistant(User $creator, ?string $handle = null): Assistant
    {
        $data = [
            'creator_id' => $creator->id,
            'release_stage' => 'draft',
            'model' => 'guide-model',
        ];

        // Omitted (not null!) so the factory's generated handle applies —
        // guide turns address the assistant by its handle.
        if (null !== $handle) {
            $data['handle'] = $handle;
        }

        return Assistant::factory()->create($data);
    }

    /**
     * One guide turn as the builder's guide chat sends it: the conversation,
     * the draft and the avatar background presets.
     *
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed> $draft
     * @param list<string> $avatarBackgrounds
     * @return array<string, mixed>
     */
    private function request(array $messages, array $draft, array $avatarBackgrounds = []): array
    {
        return [
            'messages' => $messages,
            'draft' => $draft,
            'avatarBackgrounds' => $avatarBackgrounds,
        ];
    }

    /**
     * The `{reply, updates}` payload under the endpoint's `data` envelope.
     *
     * @return array{reply: string, updates: array<string, mixed>}
     */
    private function dataOf(\Illuminate\Testing\TestResponse $response): array
    {
        /** @var array{reply: string, updates: array<string, mixed>} $data */
        $data = $response->json('data');

        return $data;
    }

    /**
     * Same provider stubbing as ChatAgentAssistantPayloadTransformationTest:
     * a real OpenAI driver with the capturing gateway installed, and an
     * in-memory model (no system default model exists, so the guide falls
     * back to the assistant's model).
     */
    private function mockProviderInfrastructure(CapturingTextGateway $gateway): void
    {
        $this->app->instance(
            LoggingMiddleware::class,
            (new LoggingMiddleware())->useServiceContainerFallback(true),
        );

        $events = $this->app->make(Dispatcher::class);

        $driver = new OpenAiProvider(
            new OpenAiGateway($events),
            ['name' => 'fake-openai', 'driver' => 'openai', 'key' => 'test-key'],
            $events,
        );
        $driver->useTextGateway($gateway);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getAdditionalDriverOptions')->willReturn([]);

        $proxy = new AiProviderProxy(
            provider: $this->createMock(AiProvider::class),
            adapter: $adapter,
            driver: $driver,
        );

        $this->mock(AiProviderProxyResolver::class, static function ($mock) use ($proxy): void {
            $mock->shouldReceive('resolveForModel')->andReturn($proxy);
        });

        $flags = $this->createMock(AiModelFlags::class);
        $flags->method('hasFeatureSamplingParameters')->willReturn(false);

        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(static fn (string $key): mixed => match ($key) {
            'flags' => $flags,
            'model_id' => 'guide-model',
            'label' => 'Guide Model',
            'active' => true,
            'parameters' => new AiModelParameters(),
            default => null,
        });

        $this->mock(AiModelRepository::class, static function ($mock) use ($model): void {
            $mock->shouldReceive('findOneOrFail')->andReturn($model);
            $mock->shouldReceive('findAll')->andReturn(new Collection([$model]));
        });
    }
}
