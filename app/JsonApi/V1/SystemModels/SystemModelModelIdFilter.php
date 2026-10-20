<?php
declare(strict_types=1);


namespace App\JsonApi\V1\SystemModels;


use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use Illuminate\Database\Eloquent\Builder;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Filters system models by the provider-qualified identifier (`<provider_id>/<model_id>`)
 * of their model, as serialized in the `model_id` field of the schema.
 */
class SystemModelModelIdFilter implements Filter
{
    use IsSingular;
    use DeserializesValue;

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        $identifier = AiModelIdentifier::fromString((string)$this->deserialize($value));
        if (!$identifier->isQualified()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('model', fn(Builder $query) => $query
            ->where('model_id', $identifier->modelId)
            ->whereHas('provider', fn(Builder $query) => $query->where('provider_id', $identifier->providerId))
        );
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'model_id';
    }
}
