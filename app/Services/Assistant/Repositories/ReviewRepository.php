<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Review;
use App\Services\Assistant\Values\ReviewStatus;

readonly class ReviewRepository
{

    public function find(Review $review): Review
    {
        return $review->load('assistant');
    }

    public function updateOrCreateForAssistant(int $assistantId, array $data): Review
    {
        return Review::updateOrCreate(
            ['assistant_id' => $assistantId],
            $data,
        );
    }

    public function update(Review $review, array $data): Review
    {
        $review->fill($data);
        $review->save();

        return $review;
    }

    public function resetReviewForAssistant(int $assistantId): void
    {
        $review = Review::where('assistant_id', $assistantId)->first();

        if ($review === null) {
            return;
        }

        $review->status = ReviewStatus::PENDING->value;
        $review->reason = "Assistant updated since last review";
        $review->save();
    }
}
