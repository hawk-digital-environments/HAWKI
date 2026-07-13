<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;

class AssistantReleaseStatus
{
    public function __construct(private readonly AssistantReviewRepository $reviewRepository)
    {
    }

    public function handle(AssistantReleaseStageChangedEvent $event): void
    {
        // Dropping back to a non-public stage tears down the review (unless it
        // is denied, which an admin must clear explicitly). Transitions into a
        // public stage are approval-driven and must not touch the review here:
        // the AssistantService records pending requests directly, and a real
        // promotion only happens once the review is APPROVED.
        if (AssistantReleaseStage::PRIVATE === $event->newStage || AssistantReleaseStage::DRAFT === $event->newStage) {
            $this->reviewRepository->deleteReviewForAssistantUnlessDenied($event->assistant->id);
        }
    }
}
