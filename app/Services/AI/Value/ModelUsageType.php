<?php

namespace App\Services\AI\Value;

enum ModelUsageType: string
{
    case DEFAULT = 'default';
    case EXTERNAL_APP = 'external_app';
}
