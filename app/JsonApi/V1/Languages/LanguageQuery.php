<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Languages;

use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class LanguageQuery extends ResourceQuery
{
    public function rules(): array
    {
        return [
            'fields' => [JsonApiRule::fieldSets()],
            'include' => [JsonApiRule::includePaths()],
        ];
    }
}
