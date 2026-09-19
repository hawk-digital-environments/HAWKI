<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Tools;

/**
 * Constrains how the model may pick tools.
 */
enum ToolChoiceMode: string
{
    case NONE = 'none';
    case AUTO = 'auto';
    case ANY = 'any';
    case TOOL = 'tool';
}
