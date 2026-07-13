<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantFeedback;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantFeedbackRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
            'assistant' => ['required', JsonApiRule::toOne()],
        ];
    }
}
