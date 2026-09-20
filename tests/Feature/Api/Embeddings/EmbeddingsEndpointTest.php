<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Embeddings;

use App\Http\Controllers\Api\V1\EmbeddingsController;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Records\UsageRecord;
use App\Models\User;
use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Factories\Contracts\VectorizerFactoryInterface;
use App\Services\Ai\Embeddings\Factories\VectorizerRegistry;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Api\Embeddings\EmbeddingsEndpointTestFixtures\FakeVectorizer;
use Tests\TestCase;

/**
 * Covers the embeddings proxy endpoint POST /api/hawki/v1/embeddings/{format?} with the
 * default openai formatter, driving the formatter → service stack against a
 * deterministic fake vectorizer.
 */
#[CoversClass(EmbeddingsController::class)]
class EmbeddingsEndpointTest extends TestCase
{
    use RefreshDatabase;
    private const string ENDPOINT = '/api/hawki/v1/embeddings';
    private const string MODEL = 'text-embedding-3-small';

    public function testGuestCannotEmbed(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertUnauthorized();
    }

    public function testItReturnsInputOrderedEmbeddings(): void
    {
        $vectorizer = $this->seedAndMockVectorizer(new EmbeddingsResponse(
            embeddings: [[0.1, 0.2], [0.3, 0.4]],
            tokens: 9,
            meta: new Meta(),
        ));

        $response = $this->postJson(self::ENDPOINT, $this->payload(input: ['first', 'second']));

        $response->assertOk()->assertExactJson([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0],
                ['object' => 'embedding', 'embedding' => [0.3, 0.4], 'index' => 1],
            ],
            'model' => self::MODEL,
            'usage' => ['prompt_tokens' => 9, 'total_tokens' => 9],
        ]);

        $received = $vectorizer->receivedRequests();
        self::assertSame(['first', 'second'], $received[0]->input);
        self::assertSame('openai', $received[0]->formatKey);
    }

    public function testItNormalisesABareStringInput(): void
    {
        $vectorizer = $this->seedAndMockVectorizer($this->singleVectorResponse());

        $this->postJson(self::ENDPOINT, $this->payload(input: 'hello'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        self::assertSame(['hello'], $vectorizer->receivedRequests()[0]->input);
    }

    public function testItForwardsDimensions(): void
    {
        $vectorizer = $this->seedAndMockVectorizer($this->singleVectorResponse());

        $this->postJson(self::ENDPOINT, $this->payload(input: 'hello', dimensions: 256))
            ->assertOk();

        self::assertSame(256, $vectorizer->receivedRequests()[0]->dimensions);
    }

    public function testItRejectsMissingModel(): void
    {
        $this->seedAndMockVectorizer($this->singleVectorResponse());

        $this->postJson(self::ENDPOINT, ['input' => 'hello'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_model')
            ->assertJsonPath('error.param', 'model');
    }

    public function testItRejectsBase64EncodingFormat(): void
    {
        $this->seedAndMockVectorizer($this->singleVectorResponse());

        $this->postJson(self::ENDPOINT, $this->payload(input: 'hello', encodingFormat: 'base64'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'unsupported_encoding_format');
    }

    public function testItRejectsUnknownModelsWith404(): void
    {
        // No fake registry: the real DefaultVectorizerFactory hits the (empty) catalogue.
        $this->actingAsUser(User::factory()->create());

        $this->postJson(self::ENDPOINT, $this->payload(model: 'does-not-exist'))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'model_not_found')
            ->assertJsonPath('error.param', 'model');
    }

    public function testItRejectsNonEmbeddingDriversWith422(): void
    {
        // Real factory + stubbed resolver whose driver lacks EmbeddingProvider.
        $this->seedModel();
        $this->actingAsUser(User::factory()->create());
        $this->stubResolverWithDriver(self::createStub(Provider::class));

        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'model_not_supported_for_embeddings')
            ->assertJsonPath('error.param', 'model');
    }

    public function testItRejectsUnknownFormats(): void
    {
        $this->seedAndMockVectorizer($this->singleVectorResponse());

        $this->postJson(self::ENDPOINT . '/llama', $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'unknown_format')
            ->assertJsonPath('error.param', 'format');
    }

    public function testItRecordsUsageAndDispatchesTheEvent(): void
    {
        Event::fake([UsageRecordedEvent::class]);
        $vectorizer = $this->seedAndMockVectorizer(new EmbeddingsResponse(
            embeddings: [[0.1]],
            tokens: 9,
            meta: new Meta(),
        ));

        $this->postJson(self::ENDPOINT, $this->payload(input: ['hello']));

        self::assertCount(1, UsageRecord::query()->where([
            'prompt_tokens' => 9,
            'completion_tokens' => 0,
            'model' => self::MODEL,
            'type' => 'private',
        ])->get());

        Event::assertDispatched(
            UsageRecordedEvent::class,
            static function (UsageRecordedEvent $event) use ($vectorizer): bool {
                return 'embeddings' === $event->channel
                    && WellKnownUsageTypes::MAIN_APP === $event->usageType
                    && self::MODEL === $event->modelId
                    && 'openai' === $event->formatKey
                    && 9 === $event->tokenUsage->promptTokens
                    && $vectorizer->model()->is($event->tokenUsage->model);
            },
        );
    }

    /**
     * Seeds provider, model, usage rule + user, and swaps the registry for one whose
     * factory always returns a {@see FakeVectorizer} answering with $vendorResponse.
     */
    private function seedAndMockVectorizer(EmbeddingsResponse $vendorResponse): FakeVectorizer
    {
        $model = $this->seedModel();
        $user = User::factory()->create();
        $vectorizer = new FakeVectorizer($vendorResponse, $model);

        $factory = new class($vectorizer) implements VectorizerFactoryInterface {
            public function __construct(private readonly VectorizerInterface $vectorizer)
            {
            }

            public function createVectorizer(EmbeddingRequest $request): ?VectorizerInterface
            {
                return $this->vectorizer;
            }
        };

        $registry = new VectorizerRegistry(new \App\Utils\Lists\LazySingletonList(
            static fn (string $factoryClass): string => 'fake_' . $factoryClass,
            static fn (string $factoryClass): VectorizerFactoryInterface => $factory,
        ));
        $registry->declare($factory::class);

        $this->app->instance(VectorizerRegistry::class, $registry);
        $this->actingAsUser($user);

        return $vectorizer;
    }

    private function seedModel(): AiModel
    {
        $provider = AiProvider::create([
            'provider_id' => 'openAi',
            'name' => 'OpenAi',
            'active' => true,
            'adapter_key' => 'openai',
            'api_key' => 'test-key',
        ]);

        $model = AiModel::create([
            'model_id' => self::MODEL,
            'label' => 'Embedding Model',
            'provider_id' => $provider->id,
            'active' => true,
            'model_type' => 'embedding',
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

    private function stubResolverWithDriver(Provider $driver): void
    {
        $resolver = new class($driver, self::createStub(\App\Services\Ai\Providers\Repositories\AiProviderRepository::class), self::createStub(\App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry::class), self::createStub(\App\Services\Ai\Providers\Adapters\DriverFactoryFactory::class), self::createStub(\App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface::class)) extends AiProviderProxyResolver {
            public function __construct(
                private readonly Provider $stubDriver,
                \App\Services\Ai\Providers\Repositories\AiProviderRepository $providerRepository,
                \App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry $adapterRegistry,
                \App\Services\Ai\Providers\Adapters\DriverFactoryFactory $driverFactoryFactory,
                private readonly \App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface $stubAdapter,
            ) {
                parent::__construct($providerRepository, $adapterRegistry, $driverFactoryFactory);
            }

            public function resolveForModel(AiModel $model): \App\Services\Ai\Providers\Values\AiProviderProxy
            {
                return new \App\Services\Ai\Providers\Values\AiProviderProxy(
                    provider: $model->provider,
                    adapter: $this->stubAdapter,
                    driver: $this->stubDriver,
                );
            }
        };

        $this->app->instance(AiProviderProxyResolver::class, $resolver);
    }

    private function singleVectorResponse(): EmbeddingsResponse
    {
        return new EmbeddingsResponse(embeddings: [[0.5]], tokens: 1, meta: new Meta());
    }

    /**
     * @param list<string>|string $input
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $model = self::MODEL,
        array|string $input = ['hello'],
        ?int $dimensions = null,
        ?string $encodingFormat = null,
    ): array {
        return array_filter([
            'model' => $model,
            'input' => $input,
            'dimensions' => $dimensions,
            'encoding_format' => $encodingFormat,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
