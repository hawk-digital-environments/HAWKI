<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

enum AssistantReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DENIED = 'denied';
    /** An admin has manually taken the assistant down, independent of a review decision. Not yet set anywhere — groundwork for a future admin "block" action. */
    case BLOCKED = 'blocked';
}
