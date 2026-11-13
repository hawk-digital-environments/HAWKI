<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ModelIdNotAvailableException extends \RuntimeException implements AiExceptionInterface
{
    /**
     * Rendered for a request that reaches no handler of its own (e.g. `/req/streamAI` naming a
     * model that was deactivated after the page loaded): the `code` lets clients show a
     * translated message instead of a generic server error.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'The requested model is not available.', 'code' => 'MODEL_UNAVAILABLE'], 422);
    }

    public static function forModelId(string|int $modelId): self
    {
        return new self(sprintf('The model with ID "%s" is not available.', $modelId));
    }
}
