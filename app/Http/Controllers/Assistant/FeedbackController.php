<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\Feedback;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class FeedbackController extends Controller
{
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;

    /**
     * Authorize reading the parent assistant via the related link, so feedback
     * cannot be used to leak a private assistant it belongs to.
     */
    public function readingRelatedAssistant(Feedback $model, $request): void
    {
        $this->authorize('view', $model->assistant);
    }

    /**
     * The feedback author is visible to anyone who may view the assistant.
     */
    public function readingRelatedUser(Feedback $model, $request): void
    {
        $this->authorize('view', $model->assistant);
    }

    /**
     * Authorize feedback creation against the parent assistant.
     * Feedback is authored by the authenticated caller (see Feedback::booted()).
     */
    public function creating($request, $query): void
    {
        $assistant = Assistant::findOrFail(
            (int) request()->input('data.relationships.assistant.data.id'),
        );

        $this->authorize('view', $assistant);
    }
}
