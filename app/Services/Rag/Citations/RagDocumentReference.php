<?php

declare(strict_types=1);

namespace App\Services\Rag\Citations;

/**
 * One source reference parsed from a RAG tool result: a managed document
 * (`documents` kind) or a direct-text attachment UUID (`attachments` kind).
 */
final class RagDocumentReference
{
    public function __construct(
        /** Either `documents` (managed adoc id) or `attachments` (uuid). */
        public readonly string $kind,
        public readonly string $id,
        public readonly string $name,
    ) {}

    /**
     * The reference's stable identity — the key the citation collector
     * dedupes by and allocates run-stable cite ids under.
     */
    public function key(): string
    {
        return $this->kind . ':' . $this->id;
    }
}
