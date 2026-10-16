<?php
declare(strict_types=1);


namespace App\JsonApi\V1\SystemModels;


use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use Illuminate\Database\Eloquent\Builder;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Filters system models by the `model_id` of their model. Accepts the provider-qualified
 * identifier (`<provider_id>/<model_id>`, as serialized by the schema) and, for backwards
 * compatibility, the plain `model_id`.
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
        $value = (string)$this->deserialize($value);
        $identifier = AiModelIdentifier::fromString($value);

        return $query->where(function (Builder $query) use ($value, $identifier) {
            $query->where('model_id', $value);
            if ($identifier->isQualified()) {
                $query->orWhereHas('model', fn(Builder $query) => $query
                    ->where('model_id', $identifier->modelId)
                    ->whereHas('provider', fn(Builder $query) => $query->where('provider_id', $identifier->providerId))
                );
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'model_id';
    }
}
