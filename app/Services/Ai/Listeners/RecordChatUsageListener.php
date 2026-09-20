<?php

declare(strict_types=1);

namespace App\Services\Ai\Listeners;

use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\UsageAnalyzerService;
use App\Services\Ai\Values\TokenUsage;
use App\Services\Ai\Values\UsageRecordContext;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Http\Request;

/**
 * Shared recording logic for the chat usage listeners.
 *
 * Only invocations whose {@see \App\Services\Ai\Agents\Values\AgentRequestContext} was
 * built with `usageRecordedViaListener` (i.e. those routed through the chat proxy
 * service) are recorded here — everything else (e.g. the legacy StreamController paths,
 * which submit their usage explicitly) is skipped to prevent double counting.
 */
abstract class RecordChatUsageListener
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly Request $request,
    ) {
    }

    protected function record(
        \Laravel\Ai\Responses\Data\Usage $usage,
        \App\Services\Ai\Agents\Values\AgentRequestContext $context,
    ): void {
        if (!$context->usageRecordedViaListener) {
            return;
        }

        $tokenUsage = TokenUsage::fromLaravelUsage($usage, $context->model);

        $this->usageAnalyzer->submitUsageRecord(
            $tokenUsage,
            new UsageRecordContext(
                type: $this->resolveType($context),
                channel: $context->channel,
                userId: $this->request->user()?->id,
                roomId: $context->roomId,
                userAgent: $this->request->userAgent(),
                formatKey: $context->formatKey,
            ),
        );

        UsageRecordedEvent::dispatch(
            tokenUsage: $tokenUsage,
            usageType: $context->usageType,
            channel: $context->channel,
            modelId: $context->model->model_id,
            formatKey: $context->formatKey,
            userAgent: $this->request->userAgent(),
            roomId: $context->roomId,
        );
    }

    /**
     * Room-scoped invocations (group chat orchestration) record as 'group' with the
     * room attributed; everything else splits by surface: external token → 'api',
     * main app → 'private'.
     */
    private function resolveType(\App\Services\Ai\Agents\Values\AgentRequestContext $context): string
    {
        if (null !== $context->roomId) {
            return 'group';
        }

        return WellKnownUsageTypes::EXTERNAL_APP === $context->usageType ? 'api' : 'private';
    }
}
