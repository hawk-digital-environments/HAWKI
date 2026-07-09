<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Traits;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTrait;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTraitTestFixtures\OpenAiModelListTraitStub;

#[CoversTrait(OpenAiModelListTrait::class)]
class OpenAiModelListTraitTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a ModelListClient whose get() call returns a ModelListResponse
     * wrapping the given JSON payload.
     */
    private function makeClient(array $payload, string|null $expectedRoute = null): ModelListClient
    {
        $rawResponse = $this->createMock(Response::class);
        $rawResponse->method('json')->willReturn($payload);
        $rawResponse->method('successful')->willReturn(true);

        $response = new ModelListResponse($rawResponse);

        $client = $this->createMock(ModelListClient::class);
        if ($expectedRoute !== null) {
            $client->expects(static::once())
                ->method('get')
                ->with($expectedRoute)
                ->willReturn($response);
        } else {
            $client->method('get')->willReturn($response);
        }

        return $client;
    }

    private function makeProvider(int $id = 1): AiProviderProxy
    {
        $model     = new AiProvider();
        $model->id = $id;

        return new AiProviderProxy(
            provider: $model,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Driver::class)
        );
    }

    // =========================================================================
    // fetchOpenAiModelList — default route
    // =========================================================================

    public function testItFetchesFromDefaultModelsRoute(): void
    {
        $sut    = new OpenAiModelListTraitStub();
        $client = $this->makeClient(['data' => [['id' => 'gpt-4']]], '/models');

        $sut->fetch($this->makeProvider(), $client);

        // assertion is encoded in the client mock expectation above
        $this->addToAssertionCount(1);
    }

    public function testItReturnsCollectionOfAiModelInstances(): void
    {
        $sut = new OpenAiModelListTraitStub();
        $client = $this->makeClient([
            'data' => [
                ['id' => 'gpt-4'],
                ['id' => 'gpt-3.5-turbo'],
            ],
        ]);

        $result = $sut->fetch($this->makeProvider(), $client);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    public function testItMapsModelIdFromResponseIdField(): void
    {
        $sut = new OpenAiModelListTraitStub();
        $client = $this->makeClient([
            'data' => [['id' => 'gpt-4o']],
        ]);

        $result = $sut->fetch($this->makeProvider(), $client);

        static::assertSame('gpt-4o', $result->first()->model_id);
    }

    public function testItReturnsEmptyCollectionWhenDataArrayIsEmpty(): void
    {
        $sut    = new OpenAiModelListTraitStub();
        $client = $this->makeClient(['data' => []]);

        $result = $sut->fetch($this->makeProvider(), $client);

        static::assertCount(0, $result);
    }

    // =========================================================================
    // fetchOpenAiModelList — alternative route
    // =========================================================================

    public function testItUsesAlternativeRouteWhenProvided(): void
    {
        $sut    = new OpenAiModelListTraitStub();
        $client = $this->makeClient(['data' => [['id' => 'model-x']]], '/v1/models');

        $sut->fetch($this->makeProvider(), $client, '/v1/models');

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // fetchOpenAiModelList — alternative mapper
    // =========================================================================

    public function testItUsesAlternativeMapperWhenProvided(): void
    {
        $sut    = new OpenAiModelListTraitStub();
        $client = $this->makeClient([
            'data' => [['id' => 'raw-id', 'extra' => 'value']],
        ]);

        $capturedItems = [];
        $mapper        = function ($item) use (&$capturedItems) {
            $capturedItems[] = $item;
            return null; // filter out — tests that the mapper, not default logic, runs
        };

        $result = $sut->fetch($this->makeProvider(), $client, null, $mapper);

        static::assertCount(1, $capturedItems);
        static::assertSame('raw-id', data_get($capturedItems[0], 'id'));
        // null returns from mapper are filtered out
        static::assertCount(0, $result);
    }

    public function testItFiltersOutNullsReturnedByAlternativeMapper(): void
    {
        $sut    = new OpenAiModelListTraitStub();
        $client = $this->makeClient([
            'data' => [
                ['id' => 'keep'],
                ['id' => 'drop'],
            ],
        ]);

        $mapper = fn($item) => data_get($item, 'id') === 'keep'
            ? new AiModel(['model_id' => 'keep'])
            : null;

        $result = $sut->fetch($this->makeProvider(), $client, null, $mapper);

        static::assertCount(1, $result);
        static::assertSame('keep', $result->first()->model_id);
    }
}
