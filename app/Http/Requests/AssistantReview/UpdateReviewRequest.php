<?php

declare(strict_types=1);

namespace App\Http\Requests\AssistantReview;

use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReviewStatus::class)],
            'reason' => ['required_if:status,denied', 'nullable', 'string'],
        ];
    }
}
