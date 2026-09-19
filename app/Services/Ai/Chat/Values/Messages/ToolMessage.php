<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

use App\Services\Ai\Chat\Values\Parts\ToolResultPart;

/**
 * Tool execution results, linked back to their calls by {@see ToolResultPart::$toolCallId}.
 */
readonly class ToolMessage implements Message
{
    public const string ROLE = 'tool';

    /**
     * @param array<int, ToolResultPart> $parts
     */
    public function __construct(
        public array $parts,
        public ?MessageMetadata $metadata = null,
    ) {
    }
}
