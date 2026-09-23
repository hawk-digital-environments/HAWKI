<?php

declare(strict_types=1);

namespace App\Services\Admin;

class ResourceCatalog
{
    public const SECTIONS = [
        'providers', 'models',
        'system-models',
        'mcp', 'tools',
        'users',
        'announcements', 'usage',
        'health', 'settings', 'environment',
    ];
}
