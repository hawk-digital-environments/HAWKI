<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Feedback;
use App\Models\User;

readonly class FeedbackRepository
{
    public function create(Assistant $assistant, User $user, string $text): Feedback
    {
        return $assistant->feedback()->create([
            'text' => $text,
            'user_id' => $user->id,
        ]);
    }
}
