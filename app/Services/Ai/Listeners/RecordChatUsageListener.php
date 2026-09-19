<?php

declare(strict_types=1);

namespace App\Services\Ai\Listeners;

use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\UsageAnalyzerService;
use App\Services\Ai\Values\TokenUsage;
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
            WellKnownUsageTypes::EXTERNAL_APP === $context->usageType ? 'api' : 'private',
        );

        UsageRecordedEvent::dispatch(
            tokenUsage: $tokenUsage,
            usageType: $context->usageType,
            channel: 'chat',
            modelId: $context->model->model_id,
            formatKey: $context->formatKey,
            userAgent: $this->request->userAgent(),
        );
    }
}
