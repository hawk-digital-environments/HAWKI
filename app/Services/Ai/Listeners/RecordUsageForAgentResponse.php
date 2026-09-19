<?php

declare(strict_types=1);

namespace App\Services\Ai\Listeners;

use App\Services\Ai\Agents\Events\AgentResponseReceivedEvent;

/**
 * Records token usage for synchronous chat invocations routed through the chat proxy
 * service. See {@see RecordChatUsageListener} for the double-counting safeguard.
 */
class RecordUsageForAgentResponse extends RecordChatUsageListener
{
    public function handle(AgentResponseReceivedEvent $event): void
    {
        $this->record($event->usage, $event->context);
    }
}
