<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Assistants\AssistantFeedback;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Defaults the author of new feedback rows to the authenticated caller.
 * Extracted from the AssistantFeedback model so the model stays a pure data
 * descriptor.
 */
class AssistantFeedbackObserver
{
    public function __construct(private readonly AuthFactory $auth)
    {
    }

    public function creating(AssistantFeedback $feedback): void
    {
        if (isset($feedback->user_id)) {
            return;
        }

        $user = $this->auth->guard()->user();

        if (!$user instanceof User) {
            return;
        }

        $feedback->user_id = $user->id;
    }
}
