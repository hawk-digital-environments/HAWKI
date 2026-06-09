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
            'data.attributes.input' => ['required', 'array', 'min:1'],
            'data.attributes.input.*.role' => ['required', 'string', 'in:user,assistant,system,developer'],
            'data.attributes.input.*.content' => ['required'],
        ];
    }
}
