<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantSettingValues;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantSettingValueRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'value' => ['nullable'],
            'assistant' => ['nullable', JsonApiRule::toOne()],
            'setting' => ['nullable', JsonApiRule::toOne()],
        ];
    }
}
