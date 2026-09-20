<?php

declare(strict_types=1);

namespace App\Services\Ai\Values;

/**
 * Full attribution context for a usage record: which surface and entry point the
 * request came from, who made it, and — for room-scoped invocations — which room.
 *
 * Passed to {@see \App\Services\Ai\UsageAnalyzerService::submitUsageRecord()}; the
 * same fields surface on {@see \App\Services\Ai\Chat\Events\UsageRecordedEvent} so
 * downstream listeners (spend reports, budget alerts) get full context without
 * re-deriving it.
 */
readonly class UsageRecordContext
{
    public function __construct(
        /**
         * Legacy caller context persisted on the record: 'private', 'group', or 'api'.
         */
        public string $type,
        /**
         * Entry-point channel ('chat', 'ui-chat', 'embeddings', 'legacy', …).
         */
        public ?string $channel = null,
        /**
         * Requesting user; falls back to the authenticated user when null.
         */
        public ?int $userId = null,
        /**
         * Room this usage is associated with, or null for direct/API calls.
         */
        public ?int $roomId = null,
        public ?string $assistantHandle = null,
        public ?string $userAgent = null,
        public ?string $formatKey = null,
    ) {
    }
}
