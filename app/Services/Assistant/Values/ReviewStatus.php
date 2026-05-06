<?php

namespace App\Services\Assistant\Values;

enum ReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DENIED = 'denied';
}
