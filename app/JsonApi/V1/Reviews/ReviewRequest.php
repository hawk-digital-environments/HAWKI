<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Reviews;

use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class ReviewRequest extends ResourceRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReviewStatus::class)],
            'reason' => ['required_if:status,denied', 'nullable', 'string'],
        ];
    }
}
