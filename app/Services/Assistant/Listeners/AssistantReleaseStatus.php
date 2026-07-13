<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Events\AssistantTriggerReleaseStatus;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;

class AssistantReleaseStatus
{
    public function __construct(private readonly AssistantReviewRepository $reviewRepository)
    {
    }

    public function handle(AssistantTriggerReleaseStatus $event): void
    {
        if (AssistantReleaseStage::PRIVATE === $event->newStage || AssistantReleaseStage::DRAFT === $event->newStage) {
            $this->reviewRepository->deleteReviewForAssistantUnlessDenied($event->assistant->id);

            return;
        }

        $this->reviewRepository->updateOrCreateForAssistant(
            $event->assistant->id,
            ['status' => AssistantReviewStatus::PENDING->value],
        );
    }
}
