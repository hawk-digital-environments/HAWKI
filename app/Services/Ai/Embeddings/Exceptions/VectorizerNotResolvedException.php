<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Exceptions;

class VectorizerNotResolvedException extends \RuntimeException implements EmbeddingExceptionInterface
{
    public static function forRequest(string $model): self
    {
        return new self(\sprintf('No vectorizer could be resolved for the embeddings request (model "%s").', $model));
    }
}
