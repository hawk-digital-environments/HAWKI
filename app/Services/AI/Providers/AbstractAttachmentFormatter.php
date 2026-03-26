<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Models\Attachment;
use App\Services\AI\Exception\AttachmentFormattingSkippedException;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Value\StoredFile;
use App\Services\Storage\Value\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Abstract class for formatting attachments for AI processing.
 * Provides methods for resolving and formatting files associated with attachments.
 */
abstract class AbstractAttachmentFormatter
{
    public function __construct(
        protected LoggerInterface    $logger,
        protected FileStorageService $fileStorage
    )
    {
    }

    /**
     * Formats a file or file extract for AI processing.
     * You can use {@see self::skip()} if the formatting failed for some reason.
     * When a file reaches this method you can assume that the model is able to handle the attachment type.
     *
     * MAY return NULL, to skip a file without including any information about the skip in the formatted result.
     *
     * @param FileInterface $file The file or extract to format.
     * @param Attachment $attachment The attachment being processed.
     * @param AiModel $model The AI model used for processing.
     * @return mixed The formatted result.
     */
    abstract protected function formatFile(
        FileInterface $file,
        Attachment    $attachment,
        AiModel       $model
    ): mixed;

    /**
     * Formats a skipped attachment, which tells the AI why an attachment has not been added, to allow better error handling.
     *
     * @param string $reason
     * @param FileInterface|null $file
     * @param Attachment $attachment
     * @param AiModel $model
     * @return mixed
     */
    abstract protected function formatSkippedFile(
        string             $reason,
        FileInterface|null $file,
        Attachment         $attachment,
        AiModel            $model
    ): mixed;

    /**
     * Resolves the files of an attachment that can be formatted.
     * Can be overridden to provide custom resolution logic.
     *
     * @param Attachment $attachment The attachment being processed.
     * @param StoredFile $file The stored file associated with the attachment.
     * @return iterable<FileInterface> The resolved files or extracts.
     */
    protected function resolveFormattableFiles(Attachment $attachment, StoredFile $file): iterable
    {
        if (empty($file->getExtracts()?->count())) {
            yield $file;
            return;
        }

        yield from $file->getExtracts();
    }

    /**
     * Skips processing of the current file.
     *
     * @param string|null $reason Optional reason for skipping the processing.
     * @throws AttachmentFormattingSkippedException Always thrown to indicate the file is skipped.
     */
    final protected function skip(string|null $reason = null): never
    {
        throw new AttachmentFormattingSkippedException($reason ?? '');
    }

    /**
     * Formats a list of attachments for AI processing.
     *
     * @param AiModel $model The AI model used for processing.
     * @param Attachment ...$attachments The attachments to format.
     * @return iterable The formatted results.
     */
    final public function format(AiModel $model, Attachment ...$attachments): iterable
    {
        foreach ($attachments as $attachment) {
            try {
                $file = $this->fileStorage->retrieve(StoredFileIdentifier::fromAttachment($attachment));
                if ($file === null) {
                    $this->skip('Could not retrieve file for attachment');
                }

                foreach ($this->resolveFormattableFiles($attachment, $file) as $formattableFile) {
                    try {
                        if (!$formattableFile instanceof FileInterface) {
                            $this->logger->error(sprintf(
                                'The Attachment formatter "%s" resolved a formattable file of type: "%s" which is not supported. Only objects implementing "%s" are allowed.',
                                static::class,
                                get_debug_type($formattableFile),
                                FileInterface::class
                            ));

                            continue;
                        }

                        $result = $this->formatFile($formattableFile, $attachment, $model);

                        if ($result === null) {
                            continue;
                        }

                        yield $result;
                    } catch (AttachmentFormattingSkippedException $e) {
                        yield from $this->handleSkipException(
                            $e,
                            $attachment,
                            $model,
                            $formattableFile
                        );
                    } catch (\Throwable $e) {
                        $this->logger->error(sprintf(
                            'An error occurred while formatting attachment "%s" with file "%s": %s',
                            $attachment->name,
                            $formattableFile->getOriginalFilename(),
                            $e->getMessage()
                        ), ['exception' => $e]);
                        $this->skip('An error occurred while formatting the file');
                    }
                }
            } catch (AttachmentFormattingSkippedException $e) {
                yield from $this->handleSkipException($e, $attachment, $model, null);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'An error occurred while processing attachment "%s": %s',
                    $attachment->name,
                    $e->getMessage()
                ), ['exception' => $e]);
            }
        }
    }

    /**
     * Convenience method to format attachments based on their UUIDs and a provided map of attachments.
     * Designed to work in combination with {@see MessageAttachmentFinder}, which returns an array of attachment UUIDs and a map of those UUIDs to Attachment instances.
     *
     * @param AiModel $model The AI model used for processing.
     * @param array<string> $attachmentUuids An array of attachment UUIDs to format.
     * @param array<string,Attachment> $attachmentsMap A map of attachment UUIDs to their corresponding Attachment instances.
     * @return iterable The formatted results for the attachments found in the map. Attachments with UUIDs not found in the map will be skipped with a warning logged.
     */
    final public function formatByAttachmentUuidsAndMap(AiModel $model, array $attachmentUuids, array $attachmentsMap): iterable
    {
        foreach ($attachmentUuids as $uuid) {
            $attachment = $attachmentsMap[$uuid] ?? null;
            if (!$attachment) {
                $this->logger->warning(sprintf(
                    'Attachment with uuid "%s" was not found in the attachments map, skipping.',
                    $uuid
                ));
                continue;
            }

            if (!$attachment instanceof Attachment) {
                $this->logger->error(sprintf(
                    'The provided attachments map contains a non-Attachment value for uuid "%s" of type "%s".',
                    $uuid,
                    get_debug_type($attachment)
                ));
                continue;
            }

            yield from $this->format($model, $attachment);
        }
    }

    /**
     * Handles exceptions for skipped attachments.
     *
     * @param AttachmentFormattingSkippedException $e The exception thrown during processing.
     * @param Attachment $attachment The attachment being processed.
     * @return mixed The formatted result for the skipped attachment.
     * @throws \RuntimeException If the exception is not related to skipping.
     */
    private function handleSkipException(
        AttachmentFormattingSkippedException $e,
        Attachment                           $attachment,
        AiModel                              $model,
        FileInterface|null                   $file
    ): mixed
    {
        if ($file === null) {
            $reason = sprintf(
                'Attachment "%s" of type %s skipped, because: %s',
                $attachment->name,
                $attachment->type,
                empty($e->getMessage()) ? 'No reason given' : $e->getMessage()
            );
        } else {
            $reason = sprintf(
                'Attachment "%s" of type %s (especially the file of: %s with type: %s) skipped, because: %s',
                $attachment->name,
                $attachment->type,
                $file->getOriginalFilename(),
                $file->getFileType()->value,
                empty($e->getMessage()) ? 'No reason given' : $e->getMessage()
            );
        }

        yield $this->formatSkippedFile(
            $reason,
            $file,
            $attachment,
            $model
        );
    }
}
