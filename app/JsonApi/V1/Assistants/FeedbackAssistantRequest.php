<?php

namespace App\JsonApi\V1\Assistants;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.attributes.text' => ['required', 'string'],
        ];
    }
}
