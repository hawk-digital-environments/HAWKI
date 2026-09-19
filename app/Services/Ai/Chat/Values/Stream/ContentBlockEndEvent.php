<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Closes the content block with the given index.
 */
readonly class ContentBlockEndEvent implements AiStreamEvent
{
    public const string TYPE = 'content_block_end';

    public function __construct(public int $blockIndex)
    {
    }
}
