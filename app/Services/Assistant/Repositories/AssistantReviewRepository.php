<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Values\AssistantReviewStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(AssistantReview::class)]
class AssistantReviewRepository extends AbstractRepository
{
    public function find(AssistantReview $review): AssistantReview
    {
        return $review->load('assistant');
    }

    public function updateOrCreateForAssistant(int $assistantId, array $data): AssistantReview
    {
        return $this->getQuery()->updateOrCreate(
            ['assistant_id' => $assistantId],
            $data,
        );
    }

    public function update(AssistantReview $review, array $data): AssistantReview
    {
        $review->fill($data);
        $review->save();

        return $review;
    }

    public function resetReviewForAssistant(int $assistantId): void
    {
        $review = $this->getQuery()->where('assistant_id', $assistantId)->first();

        if (null === $review) {
            return;
        }

        $review->status = AssistantReviewStatus::PENDING->value;
        $review->reason = 'Assistant updated since last review';
        $review->save();
    }

    public function deleteReviewForAssistantUnlessDenied(int $assistantId): void
    {
        $this->getQuery()
            ->where('assistant_id', $assistantId)
            ->where('status', '!=', AssistantReviewStatus::DENIED->value)
            ->delete();
    }
}
