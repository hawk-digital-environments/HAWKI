<?php

declare(strict_types=1);

namespace App\Services\Ai\Listeners;

use App\Services\Ai\Agents\Events\AgentStreamCompletedEvent;

/**
 * Records token usage for streamed chat invocations routed through the chat proxy
 * service. See {@see RecordChatUsageListener} for the double-counting safeguard.
 */
class RecordUsageForAgentStreamCompleted extends RecordChatUsageListener
{
    public function handle(AgentStreamCompletedEvent $event): void
    {
        $this->record($event->usage, $event->context);
    }
}
