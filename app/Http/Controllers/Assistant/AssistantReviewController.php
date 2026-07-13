<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantReviewController extends Controller
{
    use Actions\FetchMany;
    use Actions\Update;
    private ?AssistantReviewStatus $preUpdateStatus = null;

    /**
     * Capture the pre-update status to detect transitions in updated().
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function updating(AssistantReview $review, $request, $query): void
    {
        $this->preUpdateStatus = AssistantReviewStatus::tryFrom($review->status);
    }

    /**
     * When a review is denied, push the assistant back to private.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function updated(AssistantReview $review, $request, $query): void
    {
        if ($review->status !== AssistantReviewStatus::DENIED->value
            || AssistantReviewStatus::DENIED === $this->preUpdateStatus) {
            return;
        }

        $assistant = $review->assistant;
        $oldStage = AssistantReleaseStage::from($assistant->release_stage);

        $assistant->update(['release_stage' => AssistantReleaseStage::PRIVATE->value]);

        Event::dispatch(new AssistantTriggerReleaseStatus(
            $assistant,
            $oldStage,
            AssistantReleaseStage::PRIVATE,
        ));
    }
}
