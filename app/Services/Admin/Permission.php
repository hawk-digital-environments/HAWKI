<?php
declare(strict_types=1);

namespace App\Services\Admin;

enum Permission: string
{
    case ACCESS = 'admin.access';
    case USERS_VIEW = 'users.view';
    case USERS_MANAGE = 'users.manage';
    case ROLES_MANAGE = 'roles.manage';
    case MODELS_MANAGE = 'models.manage';
    case PROVIDERS_MANAGE = 'providers.manage';
    case MCP_MANAGE = 'mcp.manage';
    case ANNOUNCEMENTS_MANAGE = 'announcements.manage';
    case USAGE_VIEW = 'usage.view';
    case USAGE_PER_USER = 'usage.view-per-user';
    case HEALTH_VIEW = 'health.view';
    case HEALTH_MANAGE = 'health.manage';
    case SETTINGS_VIEW = 'settings.view';
    case SETTINGS_MANAGE = 'settings.manage';
    case EXTERNAL_APPS_MANAGE = 'external-apps.manage';

    case TOOLS_USE = 'tools.use';
    case WEB_SEARCH_USE = 'ai.capabilities.web_search.use';
    case WEB_FETCH_USE = 'ai.capabilities.web_fetch.use';
    case IMAGE_GENERATION_USE = 'ai.capabilities.image_generation.use';
    case INTERNAL_SEARCH_USE = 'tools.internal_search.use';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
