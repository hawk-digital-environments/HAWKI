<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Models\Concerns;

use App\Models\Ai\AiModel;

/**
 * Shared model-entry rendering for the models formatters: the OpenAI and Open
 * Responses dialects differ only in the creation-timestamp field name
 * (`created` vs the spec's `created_at` convention).
 */
trait FormatsModelEntries
{
    /**
     * @param string $createdAtKey the dialect's creation-timestamp field name
     *
     * @return array<string, mixed>
     */
    protected static function modelEntry(AiModel $model, string $createdAtKey): array
    {
        return array_filter([
            'id' => $model->model_id,
            'object' => 'model',
            $createdAtKey => $model->created_at?->getTimestamp() ?? 0,
            'owned_by' => $model->provider?->name,
            'label' => $model->label,
            'model_type' => $model->model_type,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
