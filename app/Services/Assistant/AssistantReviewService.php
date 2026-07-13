<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Event;

#[Singleton()]
readonly class AssistantReviewService
{
    public function __construct(
        private AssistantReviewRepository $reviewRepository,
        private AssistantRepository $assistantRepository,
    ) {
    }

    public function update(AssistantReview $review, array $data): AssistantReview
    {
        $review = $this->reviewRepository->update($review, $data);

        if (AssistantReviewStatus::from($data['status']) === AssistantReviewStatus::DENIED) {
            $assistant = $review->assistant;
            $oldStage = AssistantReleaseStage::from($assistant->release_stage);

            $this->assistantRepository->setReleaseStage($assistant, AssistantReleaseStage::PRIVATE);

            Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, AssistantReleaseStage::PRIVATE));
        }

        return $this->reviewRepository->find($review);
    }
}
