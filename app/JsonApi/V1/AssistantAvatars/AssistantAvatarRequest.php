<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantAvatars;

use App\Models\Assistants\AssistantAvatar;
use App\Rules\IconCss;
use App\Services\Assistant\Values\AssistantFieldRules;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantAvatarRequest extends ResourceRequest
{
    public function rules(): array
    {
        if ($this->isUpdating()) {
            return [
                'name' => ['sometimes', ...AssistantFieldRules::avatarEmoji()],
                'icon_css' => ['sometimes', 'string', 'max:1000', new IconCss()],
            ];
        }

        return [
            'name' => ['present', ...AssistantFieldRules::avatarEmoji()],
            'icon_css' => ['present', 'string', 'max:1000', new IconCss()],
            'assistant' => [
                'required',
                JsonApiRule::toOne(),
                // An assistant may have at most one avatar (enforced by a DB
                // unique index on assistant_id). Surface the conflict as 422
                // here rather than letting the INSERT fail with a 500.
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $assistantId = $value['id'] ?? null;

                    if (null !== $assistantId && AssistantAvatar::query()->where('assistant_id', $assistantId)->exists()) {
                        $fail('An avatar already exists for this assistant.');
                    }
                },
            ],
        ];
    }
}
