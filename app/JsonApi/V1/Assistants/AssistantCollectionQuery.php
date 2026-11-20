<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantCollectionQuery extends ResourceQuery
{
    public function rules(): array
    {
        return [
            'fields' => [JsonApiRule::fieldSets()],
            'filter' => [JsonApiRule::filter()],
            'include' => [JsonApiRule::includePaths()],
            'page' => [JsonApiRule::page()],
            'sort' => [JsonApiRule::notSupported()],
        ];
    }
}
