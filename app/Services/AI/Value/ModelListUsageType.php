<?php

namespace App\Services\AI\Value;

enum ModelListUsageType: string
{
    case DEFAULT = 'default';
    case EXTERNAL_APP = 'external_app';
}
