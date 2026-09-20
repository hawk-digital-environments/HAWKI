<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Models\Implementations\OpenResponses;

use App\Models\Ai\AiModel;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Models\Concerns\FormatsModelEntries;
use App\Services\Ai\Formatters\Models\Contracts\ModelsFormatterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Open Responses-flavoured model list (format key `openResponses`).
 *
 * **HAWKI-invented variant**: the published Open Responses specification has no
 * `/models` endpoint; this dialect follows its naming conventions instead — the
 * creation timestamp is `created_at` (as on every Open Responses resource) rather
 * than the Chat Completions `created`.
 */
readonly class OpenResponsesModelsFormatter implements ModelsFormatterInterface
{
    use FormatsModelEntries;

    public const string KEY = 'openResponses';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function formatModels(Collection $models): JsonResponse
    {
        return new JsonResponse([
            'object' => 'list',
            'data' => $models
                ->map(static fn (AiModel $model): array => self::modelEntry($model, 'created_at'))
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
