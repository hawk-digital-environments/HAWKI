<?php

namespace App\Listeners;

use App\Events\AssistantTriggerReleaseStatus;
use App\Services\Assistant\Repositories\ReviewRepository;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;

class AssistantReleaseStatus
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
    ) {}

    public function handle(AssistantTriggerReleaseStatus $event): void
    {
        if ($event->newStage === ReleaseStage::PRIVATE || $event->newStage === ReleaseStage::DRAFT) {
            return;
        }

        $this->reviewRepository->updateOrCreateForAssistant(
            $event->assistant->id,
            ['status' => ReviewStatus::PENDING->value],
        );
    }
}
