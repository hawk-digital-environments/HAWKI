<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Admin\PermissionService;

/** Code-owned grants. Display names and MCP metadata never define authorization. */
final class ToolAccessRules
{
    public const RULES = [
        'unavailable' => [],
        'web_search' => ['tools.use', 'ai.capabilities.web_search.use'],
        'image_generation' => ['tools.use', 'ai.capabilities.image_generation.use'],
        'internal_search' => ['tools.use', 'tools.internal_search.use'],
    ];

    public static function nativeRule(string $capability): ?string
    {
        return match ($capability) {
            'web_search', 'image_generation' => $capability,
            default => null,
        };
    }

    public static function allowedRules(?User $actor): array
    {
        $grants = app(PermissionService::class)->permissionsOf($actor);
        return array_keys(array_filter(self::RULES, static fn (array $required, string $name) =>
            $name !== 'unavailable' && !array_diff($required, $grants), ARRAY_FILTER_USE_BOTH));
    }

    public static function catalog(User $actor): array
    {
        $grants = app(PermissionService::class)->permissionsOf($actor);
        return array_map(static fn (string $name, array $permissions) => [
            'name' => $name,
            'title_label' => "admin.tool_access_rules.$name.title",
            'description_label' => "admin.tool_access_rules.$name.description",
            'permissions' => $permissions,
            'grantable' => !array_diff([...$permissions, 'admin.access', 'mcp.manage', 'roles.manage'], $grants),
        ], array_keys(self::RULES), array_values(self::RULES));
    }
}
