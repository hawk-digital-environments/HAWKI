<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractAiConvMessageRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function contentRules(): array
    {
        $configuredMaximum = (int) config('filesystems.upload_limits.max_attachment_files', 0);
        $maximumAttachments = 0 < $configuredMaximum ? $configuredMaximum : \PHP_INT_MAX;

        return [
            'content' => ['required', 'array'],
            'content.text' => ['required', 'array'],
            'content.text.ciphertext' => ['required', 'string'],
            'content.text.iv' => ['required', 'string'],
            'content.text.tag' => ['required', 'string'],
            'content.attachments' => ['nullable', 'array', 'max:' . $maximumAttachments],
            'content.attachments.*' => ['required_with:content.attachments', 'string'],
        ];
    }
}
