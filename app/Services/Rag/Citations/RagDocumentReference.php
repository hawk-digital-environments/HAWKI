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
        public string $name,
    ) {}
}
