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
 * The builder's guide chat action: authorization, request validation and the
 * structured-output round trip through the real agent pipeline, with the
 * provider boundary faked by a {@see CapturingTextGateway}.
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
                'categoryId' => (string)$category->id,
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
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hi'],
                    ['role' => 'assistant', 'content' => 'What should your assistant do?'],
                    ['role' => 'user', 'content' => 'Tutor first-year students in statistics.'],
                ],
                'draft' => ['name' => 'Draft name', 'starterPrompts' => [], 'model' => null],
            ],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        );

        $response->assertOk()->assertExactJson(['data' => [
            'reply' => 'I drafted a first version.',
            'updates' => [
                'handle' => 'statistics-tutor-2',
                'categoryId' => (string)$category->id,
                'model' => 'guide-model',
                'name' => 'Statistics Tutor',
                'systemPrompt' => 'You are a patient statistics tutor.',
                'starterPrompts' => ['What is a p-value?', 'Explain variance'],
            ],
        ]]);

        static::assertCount(1, $gateway->capturedSteps);
        $step = $gateway->capturedSteps[0];
        static::assertStringContainsString('"name": "Draft name"', $step['instructions']);
        static::assertStringContainsString("- {$category->id}: Teaching", $step['instructions']);
        static::assertStringContainsString('- guide-model: Guide Model', $step['instructions']);
        static::assertStringContainsString('Still empty: handle, description, categoryId, systemPrompt, model.', $step['instructions']);
        static::assertCount(3, $step['messages']);
        static::assertSame('Tutor first-year students in statistics.', $step['messages'][2]->content);
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

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            ['messages' => [['role' => 'user', 'content' => 'Build it for me']], 'draft' => []],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertOk()->assertExactJson(['data' => ['reply' => 'Done.', 'updates' => []]]);
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

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            [
                'messages' => [['role' => 'user', 'content' => 'Answer in German']],
                'draft' => ['language' => null, 'formality' => 'academic', 'answerStyle' => null],
            ],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertOk()->assertExactJson(['data' => ['reply' => 'Set the tone.', 'updates' => ['language' => 'de']]]);

        $instructions = $gateway->capturedSteps[0]['instructions'];
        static::assertStringContainsString('"formality": "academic"', $instructions);
        static::assertStringContainsString('- language: en, de', $instructions);
        static::assertStringContainsString('- formality: casual, academic', $instructions);
        static::assertStringNotContainsString('- answerStyle:', $instructions);
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

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            ['messages' => [['role' => 'user', 'content' => 'Add tags']], 'draft' => ['tags' => ['Maths']]],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertOk()->assertExactJson(['data' => [
            'reply' => 'Tagged it.',
            'updates' => ['tags' => ['Statistics', 'Tutoring', 'Maths', 'R', 'Exams']],
        ]]);

        $instructions = $gateway->capturedSteps[0]['instructions'];
        static::assertStringContainsString('"Maths"', $instructions);
        // The guide only proposes tags; creating them is the frontend's job.
        static::assertSame(1, AssistantTag::query()->count());
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
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            ['messages' => [['role' => 'user', 'content' => 'Which model do you use?']], 'draft' => []],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertOk();

        // The client validates `updates` as an object; PHP encodes an empty array as `[]`.
        static::assertStringContainsString('"updates":{}', $response->getContent());
    }

    public function testNonOwnerIsForbidden(): void
    {
        $assistant = $this->createAssistant(User::factory()->create());
        $this->actingAsUser(User::factory()->create());

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            ['messages' => [['role' => 'user', 'content' => 'Hi']], 'draft' => []],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertForbidden();
    }

    public function testLastMessageMustComeFromTheUser(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->createAssistant($owner);
        $this->actingAsUser($owner);

        $this->postJson(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/builder-guide",
            ['messages' => [['role' => 'assistant', 'content' => 'Hi']], 'draft' => []],
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
        )->assertUnprocessable();
    }

    private function createAssistant(User $creator, ?string $handle = null): Assistant
    {
        return Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => 'draft',
            'model' => 'guide-model',
            'handle' => $handle,
        ]);
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
            (new LoggingMiddleware())->useServiceContainerFallback(true)
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
        $model->method('__get')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'flags' => $flags,
                'model_id' => 'guide-model',
                'label' => 'Guide Model',
                'active' => true,
                'parameters' => new AiModelParameters(),
                default => null,
            }
        );

        $this->mock(AiModelRepository::class, static function ($mock) use ($model): void {
            $mock->shouldReceive('findOneOrFail')->andReturn($model);
            $mock->shouldReceive('findAll')->andReturn(new Collection([$model]));
        });
    }
}
