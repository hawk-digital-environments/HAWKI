<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Utils;


use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFile;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\UserMessage;

class UserMessageAttachments
{
    /**
     * @var string[]
     */
    private array $inlinedAttachments = [];
    /**
     * @var string[]
     */
    private array $skippedAttachmentMessages = [];
    /**
     * @var File[]
     */
    private array $referencedAttachments = [];
    /**
     * @var string[]
     */
    private array $errors = [];
    /**
     * @var string[]
     */
    private array $extractReferences = [];

    public function __construct(
        private readonly AgentRequestContext $context,
        private readonly MessageMetaBlocks   $meta = new MessageMetaBlocks()
    )
    {
    }

    public function register(FileInterface $file): self
    {
        if (!$this->context->model->settings->canHandleFiles()) {
            $this->addSkippedFileMessage($file, 'Attachments not allowed for this model');
            return $this;
        }

        if ($file->getFileType() === FileType::IMAGE) {
            return $this->registerImageAttachment($file);
        }

        if ($file->getFileType() === FileType::PLAIN_TEXT) {
            return $this->registerPlainTextAttachment($file);
        }

        if ($file instanceof StoredFile) {
            return $this->registerExtractsOfStoredFile($file);
        }

        $this->addSkippedFileMessage($file, 'Unsupported file type: ' . $file->getFileType()->value);
        return $this;
    }

    public function addError(string $errorMessage): self
    {
        $this->errors[] = $errorMessage;
        return $this;
    }

    private function registerImageAttachment(FileInterface $file): self
    {
        if (!$this->context->model->input->hasImage()) {
            $this->addSkippedFileMessage($file, 'Model does not support image input');
            return $this;
        }

        if (!$this->context->provider->adapter->supportsFileAsAttachment($file)) {
            $this->addSkippedFileMessage($file, 'The HAWKI provider adapter does not support this file type as attachment');
            return $this;
        }

        $this->referencedAttachments[] = Image::fromBase64(
            base64: base64_encode($file->getContent()),
            mimeType: $file->getMimeType(),
        )->as($file->getOriginalFilename());

        return $this;
    }

    private function registerPlainTextAttachment(FileInterface $file): self
    {
        if (!$this->context->model->input->hasText()) {
            // This should never happen XD, otherwise, how is it reading this?
            $this->addSkippedFileMessage($file, 'Model does not support text input');
            return $this;
        }

        if (!$this->context->provider->adapter->supportsFileAsAttachment($file)) {
            $this->addInlinedAttachment($file);
            return $this;
        }

        $this->referencedAttachments[] = Document::fromString(
            content: $file->getContent(),
            mimeType: $file->getMimeType(),
        )->as($file->getOriginalFilename());
        return $this;
    }

    private function registerExtractsOfStoredFile(StoredFile $file): self
    {
        foreach ($file->getExtracts() as $extractedFile) {
            $this->extractReferences[] = 'The file: ' . $extractedFile->getOriginalFilename() . ' was automatically extracted from the original file: ' . $file->getOriginalFilename();
            $this->register($extractedFile);
        }
        return $this;
    }

    public function apply(UserMessage $message): void
    {
        foreach ($this->referencedAttachments as $attachment) {
            $message->attachments[] = $attachment;
        }

        $contextMarkdown = $this->generateContextMarkdown();
        if (!empty($contextMarkdown)) {
            $message->content = $contextMarkdown . "\n\n" . $message->content;
        }
    }

    private function generateContextMarkdown(): string
    {
        if (!empty($this->extractReferences)) {
            $this->meta->addSection('Extracted attachments', [
                'The following attachments were automatically extracted from the original files:',
                implode("\n", $this->extractReferences)
            ]);
        }

        if (!empty($this->inlinedAttachments)) {
            $this->meta->addSection('Inlined attachments', [
                'The following attachments were inlined into the message content because they could not be sent as separate attachments:',
                implode("\n", $this->inlinedAttachments)
            ]);
        }

        if (!empty($this->skippedAttachmentMessages)) {
            $this->meta->addSection('Skipped attachments', [
                'The following attachments were skipped and not sent to the model:',
                implode("\n", $this->skippedAttachmentMessages)
            ]);
        }

        if (!empty($this->errors)) {
            $this->meta->addSection('Attachments with errors', [
                'The following errors occurred while processing attachments:',
                implode("\n", array_unique($this->errors))
            ]);
        }

        return (string)$this->meta;
    }

    private function addInlinedAttachment(FileInterface $file): void
    {
        $escapeBackticksInText = static fn(string $text): string => str_replace('```', '\`\`\`', $text);

        $this->inlinedAttachments[] = <<<MARKDOWN
- Inlined attachment: `{$file->getOriginalFilename()}` (MIME type: `{$file->getMimeType()}`, size: `{$file->getSize()} bytes`)
```
{$escapeBackticksInText($file->getContent())}
```
MARKDOWN;
    }

    private function addSkippedFileMessage(FileInterface $file, string $reason): void
    {
        $this->skippedAttachmentMessages[] = '- Skipped attachment: `' . $file->getOriginalFilename() . '` (MIME type: `' . $file->getMimeType() . '`, size: `' . $file->getSize() . ' bytes`) - Reason: ' . $reason;
    }
}
