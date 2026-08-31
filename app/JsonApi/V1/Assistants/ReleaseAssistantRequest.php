<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReleaseAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('release', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        /** @var null|Assistant $assistant */
        $assistant = $this->route('assistant');

        return [
            'data.attributes.release_stage' => [
                'required',
                'string',
                Rule::enum(AssistantReleaseStage::class),
                static function (string $attribute, mixed $value, \Closure $fail) use ($assistant): void {
                    if (!\in_array(
                        $value,
                        [AssistantReleaseStage::ORGANIZATIONAL->value, AssistantReleaseStage::FEDERATED->value],
                        true,
                    )) {
                        return;
                    }

                    $review = $assistant?->assistantReview;

                    if (null !== $review && AssistantReviewStatus::DENIED === $review->status) {
                        $fail('The assistant has a denied review and cannot be submitted for publication until an admin clears the denial.');
                    }
                },
            ],
        ];
    }

    public function releaseStage(): AssistantReleaseStage
    {
        /** @var string $value */
        $value = $this->validated('data.attributes.release_stage');

        return AssistantReleaseStage::from($value);
    }
}
