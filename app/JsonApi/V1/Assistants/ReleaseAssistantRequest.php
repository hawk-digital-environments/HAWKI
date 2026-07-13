<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Closure;
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
        /** @var Assistant|null $assistant */
        $assistant = $this->route('assistant');

        return [
            'data.attributes.release_stage' => [
                'required',
                'string',
                Rule::enum(AssistantReleaseStage::class),
                function (string $attribute, mixed $value, Closure $fail) use ($assistant): void {
                    if (!\in_array(
                        $value,
                        [AssistantReleaseStage::ORGANIZATIONAL->value, AssistantReleaseStage::FEDERATED->value],
                        true,
                    )) {
                        return;
                    }

                    $review = $assistant?->assistantReview;

                    if (null !== $review && AssistantReviewStatus::DENIED->value === $review->status) {
                        $fail('The assistant has a denied review and cannot be published until an admin clears the denial.');
                    }
                },
            ],
        ];
    }
}
