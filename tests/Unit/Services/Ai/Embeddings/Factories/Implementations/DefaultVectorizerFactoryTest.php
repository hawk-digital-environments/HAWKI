<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Embeddings\Factories\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Embeddings\Exceptions\EmbeddingNotSupportedException;
use App\Services\Ai\Embeddings\Factories\Implementations\DefaultVectorizerFactory;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(DefaultVectorizerFactory::class)]
class DefaultVectorizerFactoryTest extends TestCase
{
    private AiModelRepository&MockObject $modelRepository;
    private AiProviderProxyResolver&MockObject $providerProxyResolver;
    private DefaultVectorizerFactory $sut;
    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelRepository = $this->createMock(AiModelRepository::class);
        $this->providerProxyResolver = $this->createMock(AiProviderProxyResolver::class);

        $this->model = new AiModel(['model_id' => 'text-embedding-3-small']);

        $this->sut = new DefaultVectorizerFactory(
            modelRepository: $this->modelRepository,
            providerProxyResolver: $this->providerProxyResolver,
        );
    }

    public function testItBubblesUnknownModelsForThe404Mapping(): void
    {
        $this->modelRepository->method('findOneOrFail')->willThrowException(ModelIdNotAvailableException::forModelId('nope'));

        $this->expectException(ModelIdNotAvailableException::class);

        $this->sut->createVectorizer($this->request());
    }

    public function testItRejectsDriversWithoutEmbeddingSupport(): void
    {
        $this->resolveDriver(self::createStub(Provider::class));

        try {
            $this->sut->createVectorizer($this->request());
            self::fail('Expected EmbeddingNotSupportedException.');
        } catch (EmbeddingNotSupportedException $exception) {
            self::assertSame('model_not_supported_for_embeddings', $exception->errorCode());
            self::assertSame(422, $exception->httpStatus());
            self::assertSame('model', $exception->param());
        }
    }

    public function testItReturnsADriverVectorizerThatDelegatesToTheDriver(): void
    {
        $vendorResponse = new EmbeddingsResponse(
            embeddings: [[0.1, 0.2], [0.3, 0.4]],
            tokens: 9,
            meta: new Meta(),
        );

        $driver = self::createStub(OpenAiProvider::class);
        $driver->method('embeddings')
            ->with(
                inputs: ['first', 'second'],
                dimensions: 256,
                model: 'text-embedding-3-small',
            )
            ->willReturn($vendorResponse);
        $this->resolveDriver($driver);

        $vectorizer = $this->sut->createVectorizer($this->request(input: ['first', 'second'], dimensions: 256));

        self::assertSame($this->model, $vectorizer->model());
        self::assertSame($vendorResponse, $vectorizer->vectorize($this->request(input: ['first', 'second'], dimensions: 256)));
    }

    private function resolveDriver(Provider $driver): void
    {
        $this->modelRepository->method('findOneOrFail')->willReturn($this->model);
        $this->providerProxyResolver->method('resolveForModel')->willReturn(new AiProviderProxy(
            provider: new AiProvider(['provider_id' => 'openAi', 'name' => 'OpenAI']),
            adapter: self::createStub(ProviderAdapterInterface::class),
            driver: $driver,
        ));
    }

    /**
     * @param list<string> $input
     */
    private function request(array $input = ['hello'], ?int $dimensions = null): EmbeddingRequest
    {
        return new EmbeddingRequest(
            model: 'text-embedding-3-small',
            input: $input,
            dimensions: $dimensions,
        );
    }
}
