<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteAssistantAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('deleteAttachment', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'fileId' => 'required|string',
        ];
    }

    public function fileId(): string
    {
        return (string) $this->validated('fileId');
    }
}
