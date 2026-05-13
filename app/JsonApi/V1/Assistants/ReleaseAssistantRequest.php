<?php

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.attributes.release_stage' => ['required', 'string', Rule::enum(ReleaseStage::class)],
        ];
    }
}
