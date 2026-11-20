<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class AbstractAiConvMessageRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    final public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = $this->input('content', []);

            if (!\is_array($content)) {
                return;
            }

            if (empty($content['text']) && empty($content['attachments'])) {
                $validator->errors()->add(
                    'content',
                    'Either text or attachments must be provided in content.',
                );
            }
        });
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
            'content.text' => ['nullable', 'array'],
            'content.text.ciphertext' => ['required_with:content.text', 'string'],
            'content.text.iv' => ['required_with:content.text', 'string'],
            'content.text.tag' => ['required_with:content.text', 'string'],
            'content.attachments' => ['nullable', 'array', 'max:' . $maximumAttachments],
            'content.attachments.*' => ['required_with:content.attachments', 'string'],
        ];
    }
}
