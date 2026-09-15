<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;

/** Code-owned grants. Display names and MCP metadata never define authorization. */
final class ToolAccessRules
{
    public const RULES = [
        'unavailable' => [],
        'web_search' => [Permission::TOOLS_USE->value, Permission::WEB_SEARCH_USE->value],
        'web_fetch' => [Permission::TOOLS_USE->value, Permission::WEB_FETCH_USE->value],
        'image_generation' => [Permission::TOOLS_USE->value, Permission::IMAGE_GENERATION_USE->value],
        'internal_search' => [Permission::TOOLS_USE->value, Permission::INTERNAL_SEARCH_USE->value],
    ];

    /** Only capabilities a provider can serve natively map onto a rule of the same name. */
    public static function nativeRule(string $capability): ?string
    {
        return match ($capability) {
            WellKnownCapabilities::WEB_SEARCH,
            WellKnownCapabilities::WEB_FETCH,
            'image_generation' => $capability,
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
            'grantable' => !array_diff([...$permissions, Permission::ACCESS->value, Permission::MCP_MANAGE->value, Permission::ROLES_MANAGE->value], $grants),
        ], array_keys(self::RULES), array_values(self::RULES));
    }
}
