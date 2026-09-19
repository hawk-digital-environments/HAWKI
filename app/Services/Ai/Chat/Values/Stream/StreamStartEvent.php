<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Opens a stream: response identity is known, no content has flowed yet.
 */
readonly class StreamStartEvent implements AiStreamEvent
{
    public const string TYPE = 'stream_start';

    public function __construct(
        public string $responseId,
        public string $model,
        public ?int $created = null,
    ) {
    }
}
