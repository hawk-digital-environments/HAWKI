<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantFieldFlags;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantFieldFlagRequest extends ResourceRequest
{
    public function rules(): array
    {
        if ($this->isUpdating()) {
            // Only the resolved toggle is mutable once a flag exists.
            return [
                'resolved' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'field' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'comment' => ['required', 'string'],
            'assistant' => ['required', JsonApiRule::toOne()],
        ];
    }
}
