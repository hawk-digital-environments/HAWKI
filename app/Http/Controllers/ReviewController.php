<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\JsonApi\V1\Reviews\ReviewQuery;
use App\JsonApi\V1\Reviews\ReviewRequest;
use App\JsonApi\V1\Reviews\ReviewSchema;
use App\Models\Assistants\Review;
use App\Services\Assistant\AssistantReviewService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class ReviewController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;

    public function __construct(
        private readonly AssistantReviewService $reviewService,
    ) {
        $this->authorizeResource(Review::class, 'review');
    }

    public function update(ReviewRequest $request, ReviewSchema $schema, ReviewQuery $query, Review $review): Responsable
    {
        $this->authorize('update', $review);

        $review = $this->reviewService->update($review, $request->validated());

        return DataResponse::make($review)
            ->withQueryParameters($query);
    }

    public function destroy(Review $review): Response
    {
        $this->authorize('delete', $review);

        return response()->noContent();
    }
}
