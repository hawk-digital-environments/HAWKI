<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Review;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class ReviewRepository
{
    public function all(int $perPage = 15): LengthAwarePaginator
    {
        return Review::query()->paginate($perPage);
    }

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

    public function resetForAssistant(int $assistantId): void
    {
        $review = Review::where('assistant_id', $assistantId)->first();

        if ($review === null) {
            return;
        }

        $review->status = ReviewStatus::PENDING->value;
        $review->reason = null;
        $review->save();
    }
}
