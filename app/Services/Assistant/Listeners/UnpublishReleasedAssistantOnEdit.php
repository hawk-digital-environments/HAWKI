<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Events\AssistantUpdatedEvent;

/**
 * Editing a released assistant must never change public content without a
 * review: the first content edit demotes the assistant to private. The
 * stage-change cascade tears down its non-denied review (see
 * AssistantReleaseStatus), so the changed state only goes live again after
 * the creator resubmits and a review approves it.
 */
class UnpublishReleasedAssistantOnEdit
{
    public function __construct(private readonly AssistantService $assistantService)
    {
    }

    public function handle(AssistantUpdatedEvent $event): void
    {
        if ([] === $event->changedKeys) {
            return;
        }

        $assistant = $event->assistant;

        if (!$assistant->release_stage->isPublic()) {
            return;
        }

        $this->assistantService->revokeRelease($assistant);
    }
}
