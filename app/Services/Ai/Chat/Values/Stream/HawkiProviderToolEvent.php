<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * HAWKI extension event (`hawki:provider_tool_event`): raw provider-hosted tool
 * activity (e.g. web search progress, tool approval requests) that has no IR
 * representation of its own.
 *
 * Uses the Open Responses extension convention: clients that do not understand the
 * `hawki:` type prefix can safely ignore the event.
 */
readonly class HawkiProviderToolEvent implements AiStreamEvent
{
    public const string TYPE = 'hawki:provider_tool_event';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $itemId,
        public string $type,
        public array $data,
        public string $status,
    ) {
    }
}
