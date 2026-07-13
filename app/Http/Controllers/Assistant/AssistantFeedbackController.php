<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Concerns\AuthorizesRelatedCreation;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantFeedbackController extends Controller
{
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use AuthorizesRelatedCreation;

    /**
     * Authorize feedback creation against the parent assistant.
     * Feedback is authored by the authenticated caller (see Feedback::booted()).
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function creating($request, $query): void
    {
        $this->authorizeCreateAgainstRelatedModel('assistant', Assistant::class, 'view');
    }
}
