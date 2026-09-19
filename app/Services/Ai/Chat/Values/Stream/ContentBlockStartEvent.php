<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Opens a content block (`text`, `thinking`, `tool_use`, …).
 */
readonly class ContentBlockStartEvent implements AiStreamEvent
{
    public const string TYPE = 'content_block_start';
    public const string BLOCK_TYPE_TEXT = 'text';
    public const string BLOCK_TYPE_THINKING = 'thinking';
    public const string BLOCK_TYPE_TOOL_USE = 'tool_use';

    public function __construct(
        public int $blockIndex,
        public string $blockType,
    ) {
    }
}
