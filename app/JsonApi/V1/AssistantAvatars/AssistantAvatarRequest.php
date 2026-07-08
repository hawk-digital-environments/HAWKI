<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantAvatars;

use App\Models\Assistants\AssistantAvatar;
use App\Rules\IconCss;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantAvatarRequest extends ResourceRequest
{
    public function rules(): array
    {
        if ($this->isUpdating()) {
            return [
                'name' => ['sometimes', 'string', 'max:5'],
                'icon_css' => ['sometimes', 'string', 'max:1000', new IconCss],
            ];
        }

        return [
            'name' => ['present', 'string', 'max:5'],
            'icon_css' => ['present', 'string', 'max:1000', new IconCss],
            'assistant' => [
                'required',
                JsonApiRule::toOne(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $assistantId = request()->input('data.relationships.assistant.data.id');

                    if ($assistantId !== null && AssistantAvatar::where('assistant_id', $assistantId)->exists()) {
                        $fail('The assistant already has an avatar.');
                    }
                },
            ],
        ];
    }
}
