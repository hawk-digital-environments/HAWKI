<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantTags;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AssistantTagRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', Rule::unique('assistant_tags')],
        ];
    }
}
