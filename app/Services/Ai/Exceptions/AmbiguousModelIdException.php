<?php
declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

/**
 * Thrown when a plain `model_id` (without provider prefix) is offered by more than one provider,
 * so it cannot be resolved to a single model. Use the qualified `<provider_id>/<model_id>` form instead.
 *
 * Carries the qualified identifiers of all matching models, so callers that have to pick one
 * deterministically can use {@see getFirstCandidate()} instead of re-querying.
 */
class AmbiguousModelIdException extends \RuntimeException implements AiExceptionInterface
{
    /**
     * @var list<string>
     */
    private array $candidates;

    /**
     * @param string[] $candidates The qualified identifiers of all matching models, ordered by creation.
     */
    private function __construct(string $message, array $candidates)
    {
        parent::__construct($message);
        $this->candidates = array_values($candidates);
    }

    /**
     * @param string[] $candidates The qualified identifiers of all matching models, ordered by creation.
     */
    public static function forModelId(string $modelId, array $candidates): self
    {
        return new self(sprintf(
            'The model ID "%s" is offered by multiple providers, use one of: %s.',
            $modelId,
            implode(', ', $candidates)
        ), $candidates);
    }

    /**
     * @return list<string> The qualified identifiers of all matching models, ordered by creation.
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    /**
     * The qualified identifier of the model that was created first — the deterministic choice
     * when a caller has to pick one of the candidates.
     */
    public function getFirstCandidate(): string
    {
        return $this->candidates[0];
    }
}
