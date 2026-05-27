<?php

namespace App\Services\Assistant\Values;

enum ReleaseStage: string
{
    case DRAFT = 'draft';
    case PRIVATE = 'private';
    case ORGANIZATIONAL = 'organizational';
    case FEDERATED = 'federated';
}