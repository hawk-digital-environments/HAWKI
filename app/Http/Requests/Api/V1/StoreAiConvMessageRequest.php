<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class StoreAiConvMessageRequest extends AbstractAiConvMessageRequest
{
    public function rules(): array
    {
        return [
            'isAi' => ['required', 'boolean'],
            'threadId' => ['required', 'integer', 'min:0'],
            ...$this->contentRules(),
            'metadata' => ['nullable', 'array'],
            'model' => ['nullable', 'string', 'required_if_accepted:isAi'],
            'completion' => ['required', 'boolean'],
        ];
    }
}
