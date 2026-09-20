<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Models\Implementations\OpenAi;

use App\Models\Ai\AiModel;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Models\Concerns\FormatsModelEntries;
use App\Services\Ai\Formatters\Models\Contracts\ModelsFormatterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * OpenAI `/v1/models` wire format (format key `openai`) — the list dialect every
 * LiteLLM-compatible client speaks.
 *
 * Entries carry the four spec fields (`id`, `object`, `created`, `owned_by`) plus the
 * HAWKI extras `label` and `model_type` (harmless superset fields that help API
 * clients pick models; OpenAI clients ignore unknown keys).
 */
readonly class OpenAiModelsFormatter implements ModelsFormatterInterface
{
    use FormatsModelEntries;

    public const string KEY = 'openai';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function formatModels(Collection $models): JsonResponse
    {
        return new JsonResponse([
            'object' => 'list',
            'data' => $models
                ->map(static fn (AiModel $model): array => self::modelEntry($model, 'created'))
                ->values()
                ->all(),
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
}
