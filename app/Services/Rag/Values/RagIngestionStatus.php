<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

enum RagIngestionStatus: string
{
    case PENDING = 'pending';
    case INGESTING = 'ingesting';
    case INGESTED = 'ingested';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    /**
     * @return list<self>
     */
    public static function activeCases(): array
    {
        return [self::PENDING, self::INGESTING];
    }

    public function isActive(): bool
    {
        return \in_array($this, self::activeCases(), true);
    }

    /**
     * Whether a document for this attachment may exist in the assistant's
     * RAG dataset — the superset that must be de-ingested on deletion
     * (everything except "never applied" and "skipped").
     */
    public function mayExistInRag(): bool
    {
        return \in_array($this, [self::PENDING, self::INGESTING, self::INGESTED], true);
    }
}
