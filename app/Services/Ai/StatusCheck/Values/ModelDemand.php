<?php

namespace App\Services\Ai\StatusCheck\Values;

enum ModelDemand: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
