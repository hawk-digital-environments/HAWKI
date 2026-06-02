<?php

namespace App\JsonApi\V1\Assistants;

use Illuminate\Foundation\Http\FormRequest;

class ChatTestAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.type' => ['required', 'in:assistants'],
            'data.id' => ['required'],
            'data.attributes.messages' => ['required', 'array'],
            'data.attributes.messages.*.role' => ['required', 'string'],
            'data.attributes.messages.*.content' => ['required', 'array'],
            'data.attributes.messages.*.content.text' => ['nullable', 'string'],
            'data.attributes.tools' => ['nullable', 'array'],
            'data.attributes.params' => ['nullable', 'array'],
        ];
    }
}
