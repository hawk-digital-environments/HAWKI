<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class UpdateAiConvMessageRequest extends AbstractAiConvMessageRequest
{
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            'metadata' => ['nullable', 'array'],
            'model' => ['nullable', 'string'],
            'completion' => ['required', 'boolean'],
        ];
    }
}
