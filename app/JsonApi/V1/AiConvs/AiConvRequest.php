<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvs;

use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AiConvRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     *
     * The system prompt arrives as an opaque JSON string containing the
     * symmetrically encrypted prompt; the server never sees the plain text.
     *
     * `branched_from_slug` is backed by a foreign key, so it must reference an
     * existing conversation — and only one of the requesting user, to keep
     * conversations of other users unguessable.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string'],
            'branched_from_slug' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('ai_convs', 'slug')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
