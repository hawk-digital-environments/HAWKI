<?php

namespace App\Listeners;

use App\Events\AssistantUpdated;
use App\Services\Assistant\Repositories\ReviewRepository;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;

class ResetReviewOnUpdate
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
    ) {}

    public function handle(AssistantUpdated $event): void
    {
        if ($event->assistant->release_stage === ReleaseStage::PRIVATE->value) {
            return;
        }

        $this->reviewRepository->resetReviewForAssistant(
            $event->assistant->id,
        );
    }
}
