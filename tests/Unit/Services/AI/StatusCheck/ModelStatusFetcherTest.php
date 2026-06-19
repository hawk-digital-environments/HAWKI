<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\StatusCheck;

use App\Services\Ai\StatusCheck\ModelStatusFetcher;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ModelStatusFetcher::class)]
class ModelStatusFetcherTest extends TestCase
{
    private HttpClientInterface&MockObject $client;
    private ModelStatusFetcher $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(HttpClientInterface::class);
        $this->sut = new ModelStatusFetcher($this->client);
    }

    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(ModelStatusFetcher::class, $this->sut);
    }

    // =========================================================================
    // getClient
    // =========================================================================

    public function testItReturnsTheInjectedClient(): void
    {
        static::assertSame($this->client, $this->sut->getClient());
    }

    // =========================================================================
    // get – happy path
    // =========================================================================

    public function testItReturnsResponseOnSuccessfulGet(): void
    {
        $response = new HttpResponse(200, '{"ok":true}');

        $this->client
            ->method('request')
            ->willReturn($response);

        $result = $this->sut->get('/models');

        static::assertSame($response, $result);
    }

    public function testItPassesTheUriToTheHttpClient(): void
    {
        $response = new HttpResponse(200, '{}');

        $this->client
            ->expects(static::once())
            ->method('request')
            ->with(static::callback(
                fn(HttpRequest $req) => $req->uri === '/models'
            ))
            ->willReturn($response);

        $this->sut->get('/models');
    }

    // =========================================================================
    // get – modelStatusUri override
    // =========================================================================

    public function testItUsesOverrideUriInsteadOfPassedUri(): void
    {
        $sut = new ModelStatusFetcher($this->client, '/override-status');

        $this->client
            ->expects(static::once())
            ->method('request')
            ->with(static::callback(
                fn(HttpRequest $req) => $req->uri === '/override-status'
            ))
            ->willReturn(new HttpResponse(200, '{}'));

        $sut->get('/original-uri');
    }

    // =========================================================================
    // get – error handling
    // =========================================================================

    public function testItThrowsWhenResponseIsNotSuccessful(): void
    {
        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(503, 'Service Unavailable'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch model status from /models: Service Unavailable');

        $this->sut->get('/models');
    }

    public function testItIncludesUriInExceptionMessageOnFailure(): void
    {
        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(404, 'Not Found'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('/some/endpoint');

        $this->sut->get('/some/endpoint');
    }

    public function testItIncludesOverrideUriInExceptionMessageWhenOverrideIsSet(): void
    {
        $sut = new ModelStatusFetcher($this->client, '/override');

        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(500, 'Internal Server Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch model status from /override: Internal Server Error');

        $sut->get('/ignored-uri');
    }

    // =========================================================================
    // getExtract – happy path
    // =========================================================================

    public function testItExtractsArrayFromJsonPath(): void
    {
        $body = json_encode(['data' => [['id' => 'gpt-4'], ['id' => 'gpt-3.5']]]);

        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(200, $body));

        $result = $this->sut->getExtract('/models', 'data.*.id');

        static::assertSame(['gpt-4', 'gpt-3.5'], $result);
    }

    public function testItExtractsNestedArrayFromJsonPath(): void
    {
        $body = json_encode(['models' => [['baseModelId' => 'gemini-pro'], ['baseModelId' => 'gemini-flash']]]);

        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(200, $body));

        $result = $this->sut->getExtract('/', 'models.*.baseModelId');

        static::assertSame(['gemini-pro', 'gemini-flash'], $result);
    }

    // =========================================================================
    // getExtract – error handling
    // =========================================================================

    public function testItThrowsWhenExtractedValueIsNotAnArray(): void
    {
        $body = json_encode(['data' => 'not-an-array']);

        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(200, $body));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Extracted value at path "data" is not an array as expected');

        $this->sut->getExtract('/models', 'data');
    }

    public function testItThrowsWhenExtractedPathDoesNotExist(): void
    {
        $body = json_encode(['something' => 'else']);

        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(200, $body));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Extracted value at path "data.*.id" is not an array as expected');

        $this->sut->getExtract('/models', 'data.*.id');
    }

    public function testItThrowsWhenHttpRequestFailsInsideGetExtract(): void
    {
        $this->client
            ->method('request')
            ->willReturn(new HttpResponse(500, 'Server Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch model status from /models: Server Error');

        $this->sut->getExtract('/models', 'data.*.id');
    }

    public function testGetExtractRespectsOverrideUri(): void
    {
        $sut = new ModelStatusFetcher($this->client, '/status-override');
        $body = json_encode(['data' => [['id' => 'model-x']]]);

        $this->client
            ->expects(static::once())
            ->method('request')
            ->with(static::callback(
                fn(HttpRequest $req) => $req->uri === '/status-override'
            ))
            ->willReturn(new HttpResponse(200, $body));

        $result = $sut->getExtract('/ignored', 'data.*.id');

        static::assertSame(['model-x'], $result);
    }
}
