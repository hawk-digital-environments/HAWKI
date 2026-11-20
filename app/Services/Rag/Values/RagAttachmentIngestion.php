<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * What is sent to the RAG server for an assistant attachment: locally
 * extracted text or the original file (the RAG server's converter pipeline
 * then runs server-side).
 *
 * The single normalization point for the `rag.attachment_ingestion` config
 * value lives here — anything not explicitly "file" ingests text, so
 * config defaults and consumers never restate that rule.
 */
enum RagAttachmentIngestion: string
{
    case TEXT = 'text';

    case FILE = 'file';

    /**
     * Normalizes a raw config value (`rag.attachment_ingestion`): only the
     * exact "file" string selects file ingestion, everything else —
     * including null and unknown values — falls back to text.
     */
    public static function fromConfigString(mixed $value): self
    {
        return \is_string($value) && self::FILE->value === $value ? self::FILE : self::TEXT;
    }

    public function ingestsFiles(): bool
    {
        return self::FILE === $this;
    }
}
