<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Services\Assistant\Values\AssistantAttachmentReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewAssistantAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('reviewAttachment', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'fileId' => ['required', 'string'],
            'reviewStatus' => ['required', Rule::enum(AssistantAttachmentReviewStatus::class)],
        ];
    }

    public function fileId(): string
    {
        return (string) $this->validated('fileId');
    }

    public function reviewStatus(): AssistantAttachmentReviewStatus
    {
        return AssistantAttachmentReviewStatus::from($this->validated('reviewStatus'));
    }
}
