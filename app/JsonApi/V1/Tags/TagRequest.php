<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Tags;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class TagRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', Rule::unique('tags')],
        ];
    }
}
