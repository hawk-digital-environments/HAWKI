<?php

declare(strict_types=1);

namespace App\Services\Admin;

class ResourceCatalog
{
    public const SECTIONS = [
        'providers' => 'providers.manage', 'models' => 'models.manage',
        'system-models' => 'models.manage',
        'mcp' => 'mcp.manage', 'tools' => 'mcp.manage',
        'users' => 'users.view', 'roles' => 'roles.manage', 'mappings' => 'roles.manage',
        'announcements' => 'announcements.manage', 'usage' => 'usage.view',
        'health' => 'health.view', 'settings' => 'settings.manage', 'environment' => 'settings.view',
        'assistants' => 'assistants.manage',
    ];
}
