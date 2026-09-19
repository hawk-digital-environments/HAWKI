<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Closes a stream. Terminal; no events follow.
 */
readonly class StreamEndEvent implements AiStreamEvent
{
    public const string TYPE = 'stream_end';
}
