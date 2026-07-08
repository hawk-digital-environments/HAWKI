<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Review;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\ReviewRepository;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Event;

#[Singleton]
readonly class AssistantReviewService
{
    public function __construct(
        private ReviewRepository $reviewRepository,
        private AssistantRepository $assistantRepository,
    ) {}

    public function update(Review $review, array $data): Review
    {
        $review = $this->reviewRepository->update($review, $data);

        if (ReviewStatus::from($data['status']) === ReviewStatus::DENIED) {
            $assistant = $review->assistant;
            $oldStage = ReleaseStage::from($assistant->release_stage);

            $this->assistantRepository->setReleaseStage($assistant, ReleaseStage::PRIVATE);

            Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, ReleaseStage::PRIVATE));
        }

        return $this->reviewRepository->find($review);
    }
}
