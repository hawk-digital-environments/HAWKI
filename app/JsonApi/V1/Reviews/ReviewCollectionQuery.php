<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Reviews;

use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class ReviewCollectionQuery extends ResourceQuery
{
    public function rules(): array
    {
        return [
            'fields' => [JsonApiRule::fieldSets()],
            'include' => [JsonApiRule::includePaths()],
            'page' => [JsonApiRule::page()],
        ];
    }
}
