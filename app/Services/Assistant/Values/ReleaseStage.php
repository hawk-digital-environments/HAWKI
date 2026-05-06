<?php

namespace App\Services\Assistant\Values;

enum ReleaseStage: string
{
    case PRIVATE = 'private';
    case ORGANIZATIONAL = 'organizational';
    case FEDERATED = 'federated';
}