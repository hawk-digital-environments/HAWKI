<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Embeddings\Implementations\OpenAi;

use App\Services\Ai\Embeddings\Exceptions\InvalidEmbeddingRequestException;
use App\Services\Ai\Embeddings\Values\EmbeddingItem;
use App\Services\Ai\Embeddings\Values\EmbeddingResponse;
use App\Services\Ai\Embeddings\Values\EmbeddingUsageInfo;
use App\Services\Ai\Formatters\Embeddings\Implementations\OpenAi\OpenAiEmbeddingsFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiEmbeddingsFormatter::class)]
class OpenAiEmbeddingsFormatterTest extends TestCase
{
    private OpenAiEmbeddingsFormatter $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new OpenAiEmbeddingsFormatter();
    }

    public function testItNormalisesABareStringInputToAOneElementList(): void
    {
        $request = $this->request(['model' => 'text-embedding-3-small', 'input' => 'hello']);

        $parsed = $this->sut->parseRequest($request);

        self::assertSame('text-embedding-3-small', $parsed->model);
        self::assertSame(['hello'], $parsed->input);
        self::assertSame('openai', $parsed->formatKey);
    }

    public function testItPreservesArrayInputOrder(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'input' => ['first', 'second', 'third'],
        ]));

        self::assertSame(['first', 'second', 'third'], $parsed->input);
    }

    public function testItParsesOptionalFields(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'input' => 'hello',
            'dimensions' => 256,
            'user' => 'user-1',
        ]));

        self::assertSame(256, $parsed->dimensions);
        self::assertSame('float', $parsed->encodingFormat);
        self::assertSame('user-1', $parsed->user);
    }

    public function testItRequiresAModel(): void
    {
        $this->expectException(InvalidEmbeddingRequestException::class);
        $this->expectExceptionMessage('missing the required "model"');

        try {
            $this->sut->parseRequest($this->request(['input' => 'hello']));
        } catch (InvalidEmbeddingRequestException $exception) {
            self::assertSame('missing_model', $exception->errorCode());
            self::assertSame('model', $exception->param());

            throw $exception;
        }
    }

    public function testItRejectsEmptyInput(): void
    {
        try {
            $this->sut->parseRequest($this->request(['model' => 'm', 'input' => []]));
            self::fail('Expected InvalidEmbeddingRequestException.');
        } catch (InvalidEmbeddingRequestException $exception) {
            self::assertSame('invalid_input', $exception->errorCode());
            self::assertSame('input', $exception->param());
        }
    }

    public function testItRejectsNonStringEntriesAndNamesThem(): void
    {
        try {
            $this->sut->parseRequest($this->request(['model' => 'm', 'input' => ['ok', 5, null, 'also ok']]));
            self::fail('Expected InvalidEmbeddingRequestException.');
        } catch (InvalidEmbeddingRequestException $exception) {
            self::assertSame('invalid_input', $exception->errorCode());
            self::assertSame('input', $exception->param());
            self::assertStringContainsString('1, 2', $exception->getMessage());
        }
    }

    public function testItRejectsBase64Encoding(): void
    {
        try {
            $this->sut->parseRequest($this->request([
                'model' => 'm',
                'input' => 'hello',
                'encoding_format' => 'base64',
            ]));
            self::fail('Expected InvalidEmbeddingRequestException.');
        } catch (InvalidEmbeddingRequestException $exception) {
            self::assertSame('unsupported_encoding_format', $exception->errorCode());
            self::assertSame('encoding_format', $exception->param());
        }
    }

    public function testItRejectsInvalidDimensions(): void
    {
        try {
            $this->sut->parseRequest($this->request([
                'model' => 'm',
                'input' => 'hello',
                'dimensions' => 'big',
            ]));
            self::fail('Expected InvalidEmbeddingRequestException.');
        } catch (InvalidEmbeddingRequestException $exception) {
            self::assertSame('invalid_dimensions', $exception->errorCode());
            self::assertSame('dimensions', $exception->param());
        }
    }

    public function testItFormatsAnIndexOrderedResponse(): void
    {
        $response = new EmbeddingResponse(
            model: 'text-embedding-3-small',
            data: [
                new EmbeddingItem(embedding: [0.1, 0.2], index: 0),
                new EmbeddingItem(embedding: [0.3, 0.4], index: 1),
            ],
            usage: new EmbeddingUsageInfo(promptTokens: 12, totalTokens: 12),
        );

        $json = $this->sut->formatResponse($response);

        self::assertSame([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0],
                ['object' => 'embedding', 'embedding' => [0.3, 0.4], 'index' => 1],
            ],
            'model' => 'text-embedding-3-small',
            'usage' => ['prompt_tokens' => 12, 'total_tokens' => 12],
        ], $json->getData(true));
    }

    public function testItRendersErrorsInTheOpenAiShape(): void
    {
        $error = $this->sut->formatError(InvalidEmbeddingRequestException::forMissingModel());

        self::assertSame(400, $error->status());
        self::assertSame([
            'error' => [
                'message' => 'The request is missing the required "model" field.',
                'type' => 'invalid_request_error',
                'param' => 'model',
                'code' => 'missing_model',
            ],
        ], $error->getData(true));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create(
            '/api/hawki/v1/embeddings',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }
}
