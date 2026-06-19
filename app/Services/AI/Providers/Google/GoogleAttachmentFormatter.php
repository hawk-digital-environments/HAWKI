<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google;


use App\Models\Attachment;
use App\Services\AI\Providers\AbstractAttachmentFormatter;
use App\Services\AI\Value\AiModel;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Values\FileType;

class GoogleAttachmentFormatter extends AbstractAttachmentFormatter
{
    /**
     * @inheritDoc
     */
    protected function formatFile(FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        if ($file->getFileType() === FileType::IMAGE) {
            return [
                'inline_data' => [
                    'mime_type' => $file->getMimeType(),
                    'data' => base64_encode($file->getContent()),
                ]
            ];
        }

        if ($file->getFileType() === FileType::PLAIN_TEXT) {
            $html_safe = htmlspecialchars($file->getContent(), ENT_QUOTES, 'UTF-8');
            return [
                'text' => "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---"
            ];
        }

        $this->skip('File type not supported by this formatter');
    }

    /**
     * @inheritDoc
     */
    protected function formatSkippedFile(string $reason, ?FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        return [
            'text' => '[ERROR: ' . $reason . ']'
        ];
    }
}
