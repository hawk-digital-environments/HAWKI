<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Terminal-agnostic outcome of one ingestion poll, in HAWKI's own
 * vocabulary. Backend-specific status strings are mapped to this inside
 * each {@see \App\Services\Rag\Contracts\RagIngesterInterface} implementation.
 */
enum RagIngestionOutcome: string
{
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
