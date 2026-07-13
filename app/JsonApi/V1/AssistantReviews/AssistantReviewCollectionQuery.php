<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantReviews;

use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantReviewCollectionQuery extends ResourceQuery
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
