<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantReviews;

use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AssistantReviewRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AssistantReviewStatus::class)],
            'reason' => ['required_if:status,denied', 'nullable', 'string'],
        ];
    }
}
