<?php

namespace App\Services\Chat\Message;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class MessageContentValidator
{
    public function __construct(
        #[Config('filesystems.upload_limits.max_attachment_files')]
        private readonly int $maxAttachments = 0
    )
    {
    }
    public function validate(array $content): ?array
    {
        try{
            $rules = [
                'text' => 'nullable|array',
                'text.ciphertext' => 'required_with:text|string',
                'text.iv' => 'required_with:text|string',
                'text.tag' => 'required_with:text|string',
                
                'attachments' => 'nullable|array|max:' . ($this->maxAttachments > 0 ? $this->maxAttachments : PHP_INT_MAX),
                'attachments.*.uuid' => 'required_with:attachments|string',
                'attachments.*.name' => 'required_with:attachments|string',
                'attachments.*.mime' => 'required_with:attachments|string',
            ];

            $validator = Validator::make($content, $rules);

            $validator->after(function ($validator) use ($content) {
                $textEmpty = empty($content['text']);
                $attachmentsEmpty = empty($content['attachments']);
                if ($textEmpty && $attachmentsEmpty) {
                    $validator->errors()->add('content', 'Either text or attachments must be provided in content.');
                }
            });

            return $validator->validate();
        }
        catch (ValidationException $e) {
            Log::error($e->getMessage());
            return null;
        }
    }
}
