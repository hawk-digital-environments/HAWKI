<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Events\AssistantUpdated;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;

class AssistantResetReviewOnUpdate
{
    public function __construct(private readonly AssistantReviewRepository $reviewRepository)
    {
    }

    public function handle(AssistantUpdated $event): void
    {
        if (\in_array($event->assistant->release_stage, [AssistantReleaseStage::PRIVATE->value, AssistantReleaseStage::DRAFT->value], true)) {
            return;
        }

        $this->reviewRepository->resetReviewForAssistant($event->assistant->id);
    }
}
