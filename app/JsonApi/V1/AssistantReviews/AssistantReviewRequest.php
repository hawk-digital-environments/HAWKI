<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantReviews;

use App\Models\Assistants\AssistantFieldFlag;
use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AssistantReviewRequest extends ResourceRequest
{
    public function rules(): array
    {
        /** @var null|AssistantReview $review */
        $review = $this->route('assistant_review');

        return [
            'status' => [
                'required',
                Rule::enum(AssistantReviewStatus::class),
                // Checked here, before the framework's generic update
                // persists the attribute, not in AssistantReviewService: by
                // the time a controller hook runs the status change is
                // already saved, too late to block it.
                static function (string $attribute, mixed $value, \Closure $fail) use ($review): void {
                    if (AssistantReviewStatus::APPROVED->value !== $value || null === $review) {
                        return;
                    }

                    $hasUnresolvedFlags = AssistantFieldFlag::query()
                        ->where('assistant_id', $review->assistant_id)
                        ->where('resolved', false)
                        ->exists();

                    if ($hasUnresolvedFlags) {
                        $fail('Unresolved review flags must be cleared before approving.');
                    }
                },
            ],
            'reason' => [
                'required_if:status,' . AssistantReviewStatus::DENIED->value . ',' . AssistantReviewStatus::NEEDS_REVISION->value,
                'nullable',
                'string',
            ],
        ];
    }
}
