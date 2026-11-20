<?php

declare(strict_types=1);

namespace App\Services\Rag\Exceptions;

/**
 * A RAG server request failed permanently (4xx or unexpected response
 * shape). Transient transport-level failures (timeouts, connection errors)
 * surface as the underlying Http client exceptions instead, so callers can
 * distinguish "retry later" from "give up".
 */
class RagIngestionRequestException extends \RuntimeException implements RagExceptionInterface
{
    private function __construct(
        string $message,
        private readonly int $statusCode = 0,
    ) {
        parent::__construct($message);
    }

    public static function forFailedResponse(string $method, string $url, int $status, string $body): self
    {
        return new self(
            \sprintf(
                'RAG server rejected %s %s with status %d: %s',
                $method,
                $url,
                $status,
                $body,
            ),
            $status,
        );
    }

    public static function forMissingTaskId(string $method, string $url): self
    {
        return new self(\sprintf(
            'RAG server response for %s %s did not contain a task_id.',
            $method,
            $url,
        ));
    }

    public static function forMissingDocumentId(string $method, string $url): self
    {
        return new self(\sprintf(
            'RAG server response for %s %s did not contain a document_id.',
            $method,
            $url,
        ));
    }

    public static function forMissingSourceId(string $method, string $url): self
    {
        return new self(\sprintf(
            'RAG server response for %s %s did not contain a source_id.',
            $method,
            $url,
        ));
    }

    /**
     * Server-side or infrastructure failures are worth retrying later;
     * 4xx responses are permanent — except throttling (429), which is a
     * "try again later" answer, not a rejection.
     */
    public function isTransient(): bool
    {
        return $this->statusCode >= 500 || 429 === $this->statusCode || 0 === $this->statusCode;
    }
}
