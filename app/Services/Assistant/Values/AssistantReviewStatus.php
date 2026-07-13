<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

enum AssistantReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DENIED = 'denied';
}
