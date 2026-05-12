<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssistantReview\UpdateReviewRequest;
use App\Http\Resources\AssistantReview\AssistantReviewResource;
use App\Models\Assistants\Review;
use App\Services\Assistant\AssistantReviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssistantReviewController extends Controller
{
    use ApiTrait;

    public function __construct(
        private readonly AssistantReviewService $reviewService,
    ) {
        $this->authorizeResource(Review::class, 'review');
    }

    public function index(): AnonymousResourceCollection
    {
        $reviews = $this->reviewService->list($this->pageSize());

        return AssistantReviewResource::collection(
            $this->applyPagination($reviews)
        );
    }

    public function update(UpdateReviewRequest $request, Review $review): AssistantReviewResource
    {
        $review = $this->reviewService->update(
            $review,
            $request->validated(),
        );

        return (new AssistantReviewResource($review))
            ->includePreviouslyLoadedRelationships();
    }
}
