<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


/**
 * Thrown when a plain `model_id` (without provider prefix) is offered by more than one provider,
 * so it cannot be resolved to a single model. Use the qualified `<provider_id>/<model_id>` form instead.
 */
class AmbiguousModelIdException extends \RuntimeException implements AiExceptionInterface
{
    /**
     * @param string[] $candidates The qualified identifiers of all matching models.
     */
    public static function forModelId(string $modelId, array $candidates): self
    {
        return new self(sprintf(
            'The model ID "%s" is offered by multiple providers, use one of: %s.',
            $modelId,
            implode(', ', $candidates)
        ));
    }
}
