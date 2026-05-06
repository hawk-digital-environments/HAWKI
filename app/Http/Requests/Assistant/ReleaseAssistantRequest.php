<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

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
            'release_stage' => ['required', Rule::enum(ReleaseStage::class)],
        ];
    }
}
