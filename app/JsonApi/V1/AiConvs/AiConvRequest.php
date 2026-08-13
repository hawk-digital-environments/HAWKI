<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvs;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AiConvRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     *
     * The system prompt arrives as an opaque JSON string containing the
     * symmetrically encrypted prompt; the server never sees the plain text.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
