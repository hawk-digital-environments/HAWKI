<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Ai\AiModel;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * `filter[model_available]=true` keeps only assistants whose model is still offered;
 * `false` keeps only the ones whose model is not (deactivated, removed or not usable by
 * the current user). Such an assistant is deactivated until its creator picks a new model.
 *
 * The subquery goes through {@see AiModel}'s contextual scopes (active model, active
 * provider, usage rules), so "offered" means exactly what the `ai-models` list shows.
 */
class AssistantModelAvailableFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'model_available';
    }

    public function apply($query, $value)
    {
        $offeredModelIds = AiModel::query()->select('model_id');

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN)
            ? $query->whereIn('model', $offeredModelIds)
            : $query->whereNotIn('model', $offeredModelIds);
    }
}
