<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Review;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class ReviewController extends Controller
{
    use Actions\FetchMany;
    use Actions\Update;

    private ?ReviewStatus $preUpdateStatus = null;

    public function __construct()
    {
        $this->authorizeResource(Review::class, 'assistant_review');
    }

    /**
     * Capture the pre-update status to detect transitions in updated().
     */
    public function updating(Review $review, $request, $query): void
    {
        $this->preUpdateStatus = ReviewStatus::tryFrom($review->status);
    }

    /**
     * When a review is denied, push the assistant back to private.
     */
    public function updated(Review $review, $request, $query): void
    {
        if ($review->status !== ReviewStatus::DENIED->value) {
            return;
        }

        $assistant = $review->assistant;
        $oldStage = ReleaseStage::from($assistant->release_stage);

        $assistant->update(['release_stage' => ReleaseStage::PRIVATE->value]);

        Event::dispatch(new AssistantTriggerReleaseStatus(
            $assistant,
            $oldStage,
            ReleaseStage::PRIVATE,
        ));
    }
}
