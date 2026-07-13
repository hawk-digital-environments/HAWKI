<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\AssistantReviewService;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Http\Request;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantReviewController extends Controller
{
    use Actions\FetchMany;
    use Actions\Update;
    private ?AssistantReviewStatus $preUpdateStatus = null;

    public function __construct(private readonly AssistantReviewService $reviewService)
    {
    }

    /**
     * Capture the pre-update status to detect transitions in updated().
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function updating(AssistantReview $review, $request, $query): void
    {
        // The status attribute is cast to AssistantReviewStatus on the model;
        // getOriginal returns the pre-fill value as the same enum (Laravel
        // honours casts for originals).
        $original = $review->getOriginal('status');
        $this->preUpdateStatus = $original instanceof AssistantReviewStatus ? $original : null;
    }

    /**
     * React to review status transitions by delegating to the review service,
     * which promotes the assistant on approval and demotes it on denial. The
     * acting reviewer (and optional denial reason) is forwarded so the audit
     * trail records who made the decision. Transition detection lives in
     * {@see AssistantReviewService::applyStatusTransition()}.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function updated(AssistantReview $review, $request, $query): void
    {
        $reviewer = $request instanceof Request ? $request->user() : null;

        $this->reviewService->applyStatusTransition($review, $this->preUpdateStatus, $reviewer);
    }
}
