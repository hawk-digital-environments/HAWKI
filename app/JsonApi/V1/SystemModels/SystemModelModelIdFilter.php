<?php
declare(strict_types=1);


namespace App\JsonApi\V1\SystemModels;


use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use Illuminate\Database\Eloquent\Builder;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Filters system models by the provider-qualified identifier (`<provider_id>/<model_id>`)
 * of their model, as serialized in the `model_id` field of the schema. The condition itself
 * is owned by {@see AiModelRepository::whereIdentifier()}, so the identifier translation
 * lives in one place; an unqualified value matches nothing.
 */
class SystemModelModelIdFilter implements Filter
{
    use IsSingular;
    use DeserializesValue;

    public function __construct(private readonly AiModelRepository $modelRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        $identifier = AiModelIdentifier::fromString((string)$this->deserialize($value));

        /** @phpstan-ignore argument.type (larastan types the whereHas callback builder as Builder<Model>, for the `model` relation it is Builder<AiModel>) */
        return $query->whereHas('model', fn(Builder $query) => $this->modelRepository->whereIdentifier($query, $identifier));
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'model_id';
    }
}
