<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Embeddings\Implementations\OpenAi;

use App\Services\Ai\Embeddings\Exceptions\InvalidEmbeddingRequestException;
use App\Services\Ai\Embeddings\Values\EmbeddingItem;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Embeddings\Values\EmbeddingResponse;
use App\Services\Ai\Formatters\Embeddings\Contracts\EmbeddingFormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OpenAI `/v1/embeddings` wire format (format key `openai`): the canonical embeddings
 * dialect every OpenAI-compatible client speaks.
 *
 * v1 constraints: `model` is required (no system-default fallback), `input` is a string
 * or a list of strings (token-int arrays are rejected), `encoding_format: "base64"` is
 * rejected. `data[]` is emitted index-ordered to match `input[]` — the ordering
 * guarantee that is the core of this format.
 */
readonly class OpenAiEmbeddingsFormatter implements EmbeddingFormatterInterface
{
    public const string KEY = 'openai';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function parseRequest(Request $request): EmbeddingRequest
    {
        $body = $request->json()->all();

        if (!\is_array($body) || [] === $body) {
            throw InvalidEmbeddingRequestException::forInvalidInput();
        }

        $model = $body['model'] ?? null;

        if (!\is_string($model) || '' === $model) {
            throw InvalidEmbeddingRequestException::forMissingModel();
        }

        $input = $this->parseInput($body['input'] ?? null);

        $encodingFormat = $body['encoding_format'] ?? 'float';

        if (!\is_string($encodingFormat) || '' === $encodingFormat) {
            $encodingFormat = 'float';
        }

        if ('float' !== $encodingFormat) {
            throw InvalidEmbeddingRequestException::forUnsupportedEncodingFormat($encodingFormat);
        }

        $dimensions = $body['dimensions'] ?? null;

        if (null !== $dimensions && (!\is_int($dimensions) || 1 > $dimensions)) {
            throw InvalidEmbeddingRequestException::forInvalidDimensions($dimensions);
        }

        return new EmbeddingRequest(
            model: $model,
            input: $input,
            dimensions: $dimensions,
            encodingFormat: $encodingFormat,
            user: \is_string($body['user'] ?? null) ? $body['user'] : null,
            formatKey: self::KEY,
        );
    }

    public function formatResponse(EmbeddingResponse $response): JsonResponse
    {
        return new JsonResponse([
            'object' => 'list',
            'data' => array_map(
                static fn (EmbeddingItem $item): array => [
                    'object' => 'embedding',
                    'embedding' => $item->embedding,
                    'index' => $item->index,
                ],
                $response->data,
            ),
            'model' => $response->model,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens,
                'total_tokens' => $response->usage->totalTokens,
            ],
        ]);
    }

    public function formatError(FormatterRequestException $exception): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'message' => $exception->getMessage(),
                'type' => $exception->errorType(),
                'param' => $exception->param(),
                'code' => $exception->errorCode(),
            ],
        ], $exception->httpStatus());
    }

    /**
     * Normalises the wire `input` (bare string or list of strings) to a list of
     * non-empty strings.
     *
     * @return list<string>
     */
    private function parseInput(mixed $input): array
    {
        if (\is_string($input)) {
            $input = [$input];
        }

        if (!\is_array($input) || [] === $input) {
            throw InvalidEmbeddingRequestException::forInvalidInput();
        }

        $offending = [];

        foreach ($input as $index => $entry) {
            if (!\is_string($entry) || '' === trim($entry)) {
                $offending[] = $index;
            }
        }

        if ([] !== $offending) {
            throw InvalidEmbeddingRequestException::forInvalidInput($offending);
        }

        return array_values($input);
    }
}
