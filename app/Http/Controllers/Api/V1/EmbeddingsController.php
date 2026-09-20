<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\Embeddings\EmbeddingService;
use App\Services\Ai\Embeddings\Exceptions\InvalidEmbeddingRequestException;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Formatters\Embeddings\Contracts\EmbeddingFormatterInterface;
use App\Services\Ai\Formatters\Embeddings\EmbeddingFormatterRegistry;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\UnknownModelException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic embeddings proxy endpoint: `POST /api/hawki/v1/embeddings/{format?}`.
 *
 * Deliberately thin, mirroring {@see ChatController}: resolves the requested wire format
 * (default `openai`), delegates parsing to the formatter, execution to
 * {@see EmbeddingService}, and rendering back to the formatter. Formatter-level request
 * errors are rendered in the formatter's own error shape so wire-format clients receive
 * spec-compliant error payloads.
 *
 * Unlike the chat endpoint (D10), an explicit-but-unknown `{format}` segment errors
 * immediately instead of falling back to the default formatter — the lenient fallback is
 * only correct while a single format exists.
 */
class EmbeddingsController extends Controller
{
    public function __construct(
        private readonly EmbeddingFormatterRegistry $formatters,
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    public function __invoke(Request $request, ?string $format = null): Response
    {
        if (null !== $format && !$this->formatters->has($format)) {
            return $this->formatters->resolve()->formatError(
                InvalidEmbeddingRequestException::forUnknownFormat($format),
            );
        }

        $formatter = $this->formatters->resolve($format);

        try {
            $embeddingRequest = $formatter->parseRequest($request);
            $embeddingResponse = $this->embeddingService->send($embeddingRequest);
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        } catch (ModelIdNotAvailableException $exception) {
            return $formatter->formatError(UnknownModelException::fromModelException($exception));
        }

        return $formatter->formatResponse($embeddingResponse);
    }
}
