<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;

enum ModelCapabilityValueType: string
{
    /**
     * Prefer tool with the specific capability if available, use native if available.
     */
    case YES = 'yes';
    /**
     * Disable this capability.
     */
    case NO = 'no';
    /**
     * Use the native capability if available, ignore tools.
     */
    case NATIVE = 'native';
    /**
     * Use a tool with the specific capability if available, ignore native.
     */
    case TOOL = 'tool';
}
