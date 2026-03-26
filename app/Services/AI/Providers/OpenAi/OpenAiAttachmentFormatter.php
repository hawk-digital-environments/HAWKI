<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi;


use App\Models\Attachment;
use App\Services\AI\Providers\AbstractAttachmentFormatter;
use App\Services\AI\Value\AiModel;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Value\FileType;

class OpenAiAttachmentFormatter extends AbstractAttachmentFormatter
{
    /**
     * @inheritDoc
     */
    protected function formatFile(FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        if ($file->getFileType() === FileType::IMAGE) {
            if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                $this->skip('The file was presented as an image with an unsupported MIME type: ' . $file->getMimeType());
            }
            $base64 = base64_encode($file->getContent());
            return [
                'type' => 'input_image',
                'image_url' => "data:{$file->getMimeType()};base64,{$base64}",
            ];
        }

        if ($file->getFileType() === FileType::PLAIN_TEXT) {
            $html_safe = htmlspecialchars($file->getContent(), ENT_QUOTES, 'UTF-8');
            return [
                'type' => 'input_text',
                'text' => "[ATTACHED FILE: {$file->getOriginalFilename()}]\n---\n{$html_safe}\n---"
            ];
        }

        $this->skip('File type not supported by this formatter');
    }

    /**
     * @inheritDoc
     */
    protected function formatSkippedFile(string $reason, FileInterface|null $file, Attachment $attachment, AiModel $model): mixed
    {
        return [
            'type' => 'input_text',
            'text' => $reason
        ];
    }
}
