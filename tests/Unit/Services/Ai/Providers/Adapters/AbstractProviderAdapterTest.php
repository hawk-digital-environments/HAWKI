<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AbstractProviderAdapter::class)]
class AbstractProviderAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns a minimal concrete subclass that fulfils the two abstract requirements
     * (createDriver + getModels) so the adapter itself can be tested in isolation.
     */
    private function makeAdapter(Collection|null $models = null): AbstractProviderAdapter
    {
        $models ??= new Collection();

        return new class($models) extends AbstractProviderAdapter {
            public function __construct(private Collection $modelCollection)
            {
            }

            public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
            {
                return $this->createMock(Driver::class);
            }

            public function getModels(AiProviderProxy $provider): Collection
            {
                return $this->modelCollection;
            }

            // Expose protected methods for testing
            public function callAssertNonEmptyApiUrl(string|null $url, AgentRequestContext|AiProvider $context): string
            {
                return $this->assertNonEmptyApiUrl($url, $context);
            }

            public function callCreateModelListClient(PendingRequest $request): ModelListClient
            {
                return $this->createModelListClient($request);
            }
        };
    }

    /**
     * Builds a real {@see AiProviderProxy} value object around the given (or a default)
     * {@see AiProvider} Eloquent model, mocking only the adapter and driver dependencies.
     */
    private function makeProviderProxy(AiProvider|null $provider = null): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: $provider ?? new AiProvider(['name' => 'TestProvider', 'adapter_key' => 'test']),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Driver::class),
        );
    }

    /**
     * Builds a real {@see AgentRequestContext} value object with real value-object dependencies.
     */
    private function makeRequestContext(AiProvider|null $provider = null): AgentRequestContext
    {
        return new AgentRequestContext(
            provider: $this->makeProviderProxy($provider),
            model: new AiModel(['model_id' => 'test-model']),
            modelParameters: new AiModelParameters(),
        );
    }

    // =========================================================================
    // Default optional-method return values
    // =========================================================================

    public function testItGetNameLabelReturnsNullByDefault(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNameLabel());
    }

    public function testItGetDescriptionLabelReturnsNullByDefault(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getDescriptionLabel());
    }

    public function testItGetNativeToolFactoryForCapabilityReturnsNullByDefault(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNativeToolFactoryForCapability('web_search'));
    }

    public function testItGetAdditionalDriverOptionsReturnsEmptyArrayByDefault(): void
    {
        $sut = $this->makeAdapter();
        $options = $sut->getAdditionalDriverOptions(
            $this->createMock(Agent::class),
            $this->makeRequestContext(),
        );
        static::assertSame([], $options);
    }

    public function testItSupportsFileAsAttachmentReturnsTrueByDefault(): void
    {
        $sut = $this->makeAdapter();
        static::assertTrue($sut->supportsFileAsAttachment($this->createMock(FileInterface::class)));
    }

    // =========================================================================
    // checkModelStatus — default implementation
    // =========================================================================

    public function testItCheckModelStatusMarksAllModelsOnline(): void
    {
        $modelA = new AiModel(['model_id' => 'model-a']);
        $modelB = new AiModel(['model_id' => 'model-b']);
        $sut = $this->makeAdapter(new Collection([$modelA, $modelB]));

        $statusCollection = $this->createMock(AiModelOnlineStatusCollection::class);
        $statusCollection->expects(static::exactly(2))
            ->method('setOnline')
            ->with(static::logicalOr(
                static::equalTo('model-a'),
                static::equalTo('model-b'),
            ));

        $sut->checkModelStatus(
            $statusCollection,
            $this->createMock(AiModelDemandCollection::class),
            $this->makeProviderProxy(),
        );
    }

    public function testItCheckModelStatusDoesNothingWhenNoModelsReturned(): void
    {
        $sut = $this->makeAdapter(new Collection());

        $statusCollection = $this->createMock(AiModelOnlineStatusCollection::class);
        $statusCollection->expects(static::never())->method('setOnline');

        $sut->checkModelStatus(
            $statusCollection,
            $this->createMock(AiModelDemandCollection::class),
            $this->makeProviderProxy(),
        );
    }

    // =========================================================================
    // assertNonEmptyApiUrl
    // =========================================================================

    public function testItAssertNonEmptyApiUrlReturnsUrlWhenNonEmpty(): void
    {
        $sut = $this->makeAdapter();
        $provider = new AiProvider(['name' => 'MyProvider', 'adapter_key' => 'openai']);

        $result = $sut->callAssertNonEmptyApiUrl('https://api.example.com', $provider);

        static::assertSame('https://api.example.com', $result);
    }

    public function testItAssertNonEmptyApiUrlThrowsWhenUrlIsNull(): void
    {
        $sut = $this->makeAdapter();
        $provider = new AiProvider(['name' => 'MyProvider', 'adapter_key' => 'openai']);

        $this->expectException(InvalidProviderConfigurationException::class);
        $this->expectExceptionMessage(
            sprintf(
                'API URL is required for provider "%s" with adapter key "%s".',
                'MyProvider',
                'openai'
            )
        );

        $sut->callAssertNonEmptyApiUrl(null, $provider);
    }

    public function testItAssertNonEmptyApiUrlThrowsWhenUrlIsEmptyString(): void
    {
        $sut = $this->makeAdapter();
        $provider = new AiProvider(['name' => 'OtherProvider', 'adapter_key' => 'ollama']);

        $this->expectException(InvalidProviderConfigurationException::class);

        $sut->callAssertNonEmptyApiUrl('', $provider);
    }

    public function testItAssertNonEmptyApiUrlExtractsProviderFromAgentRequestContext(): void
    {
        $sut = $this->makeAdapter();

        $provider = new AiProvider(['name' => 'CtxProvider', 'adapter_key' => 'ctx_key']);
        $context = $this->makeRequestContext($provider);

        $this->expectException(InvalidProviderConfigurationException::class);
        $this->expectExceptionMessage('API URL is required for provider "CtxProvider" with adapter key "ctx_key".');

        $sut->callAssertNonEmptyApiUrl(null, $context);
    }

    // =========================================================================
    // createModelListClient
    // =========================================================================

    public function testItCreateModelListClientReturnsModelListClient(): void
    {
        $sut = $this->makeAdapter();
        $request = $this->createMock(PendingRequest::class);

        $client = $sut->callCreateModelListClient($request);

        static::assertInstanceOf(ModelListClient::class, $client);
    }
}
