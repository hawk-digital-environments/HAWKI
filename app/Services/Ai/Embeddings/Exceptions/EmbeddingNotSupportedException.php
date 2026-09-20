<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Exceptions;

use App\Models\Ai\AiModel;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;

/**
 * The resolved model's provider driver cannot produce embeddings.
 *
 * Rendered as a 422 — the model exists (a sibling error, the unknown-model 404, covers
 * catalogue misses) but its driver does not implement
 * {@see \Laravel\Ai\Contracts\Providers\EmbeddingProvider}.
 */
class EmbeddingNotSupportedException extends FormatterRequestException implements EmbeddingExceptionInterface
{
    public static function forModel(AiModel $model): self
    {
        return new self(
            \sprintf('The model "%s" does not support embeddings.', $model->model_id),
            errorCode: 'model_not_supported_for_embeddings',
            httpStatus: 422,
            param: 'model',
        );
    }
}
