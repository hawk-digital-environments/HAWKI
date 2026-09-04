<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvs;

use App\Models\Assistants\Assistant;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AiConvRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     *
     * The system prompt arrives as an opaque JSON string containing the
     * symmetrically encrypted prompt; the server never sees the plain text.
     *
     * The assistant handle optionally binds the conversation to an assistant
     * (plaintext metadata, not chat content). Only assistants visible to the
     * requesting user may be bound.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string'],
            'assistant_handle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'exists:assistants,handle',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!\is_string($value) || $value === '') {
                        return;
                    }

                    $assistant = Assistant::query()->where('handle', $value)->first();

                    if ($assistant !== null && !$this->user()?->can('view', $assistant)) {
                        $fail('The selected assistant is not accessible.');
                    }
                },
            ],
        ];
    }
}
