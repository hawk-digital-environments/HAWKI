<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Ollama;


use App\Models\Attachment;
use App\Services\AI\Providers\AbstractAttachmentFormatter;
use App\Services\AI\Value\AiModel;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Value\FileType;
use App\Services\Storage\Value\StoredFile;

class OllamaAttachmentFormatter extends AbstractAttachmentFormatter
{
    private array $images = [];

    /**
     * Get the formatted images as base64-encoded strings.
     *
     * @return array An array of base64-encoded image strings.
     */
    public function getFormattedImages(): array
    {
        return $this->images;
    }

    /**
     * @inheritDoc
     */
    protected function formatFile(FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        if ($file->getFileType() === FileType::IMAGE) {
            $this->images[] = base64_encode($file->getContent());
            return null;
        }

        if ($file->getFileType() === FileType::PLAIN_TEXT) {
            return "\n\n" . $file->getContent();
        }

        $this->skip('File type not supported by this formatter');
    }

    /**
     * @inheritDoc
     */
    protected function resolveFormattableFiles(Attachment $attachment, StoredFile $file): iterable
    {
        $this->images = [];
        return parent::resolveFormattableFiles($attachment, $file);
    }

    /**
     * @inheritDoc
     */
    protected function formatSkippedFile(string $reason, ?FileInterface $file, Attachment $attachment, AiModel $model): mixed
    {
        return "\n\n[NOTE: $reason]";
    }
}
