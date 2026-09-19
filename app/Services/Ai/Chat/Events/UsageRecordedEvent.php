<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Events;

use App\Services\Ai\Values\TokenUsage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a token-usage record has been persisted for a chat invocation.
 *
 * Fired by the usage recording listeners ({@see \App\Services\Ai\Listeners\RecordChatUsageListener})
 * so downstream listeners (spend reports, budget alerts, quota enforcement) can react
 * with full context about the originating request.
 */
readonly class UsageRecordedEvent
{
    use Dispatchable;

    public function __construct(
        public TokenUsage $tokenUsage,
        /**
         * Surface the request came from ('main' / 'external').
         */
        public string $usageType,
        /**
         * Entry point channel ('chat').
         */
        public string $channel,
        public string $modelId,
        public ?string $formatKey = null,
        public ?string $userAgent = null,
    ) {
    }
}
