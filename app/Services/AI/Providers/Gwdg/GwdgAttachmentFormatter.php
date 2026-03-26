<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg;


use App\Models\Attachment;
use App\Services\AI\Providers\AbstractAttachmentFormatter;
use App\Services\AI\Value\AiModel;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Value\FileType;

class GwdgAttachmentFormatter extends AbstractAttachmentFormatter
{
    /**
     * @inheritDoc
     */
    protected function formatFile(FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        if ($file->getFileType() === FileType::IMAGE) {
            $imageData = base64_encode($file->getContent());
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$file->getMimeType()};base64,{$imageData}",
                ],
            ];
        }

        if ($file->getFileType() === FileType::PLAIN_TEXT) {
            $html_safe = htmlspecialchars($file->getContent(), ENT_QUOTES, 'UTF-8');
            return [
                'type' => 'text',
                'text' => "[ATTACHED FILE: {$file->getOriginalFilename()}]\n---\n{$html_safe}\n---"
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
            'type' => 'text',
            'text' => $reason
        ];
    }
}
