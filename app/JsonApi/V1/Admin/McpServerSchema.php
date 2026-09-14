<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\McpServerRepository;

final class McpServerSchema extends Schema
{
    protected const REPOSITORY = McpServerRepository::class;
    protected const ATTRIBUTES = [
        'server_label', 'kind', 'url', 'status', 'api_key_set', 'additional_config_set', 'description', 'require_approval', 'timeouts',
    ];
    public static string $model = Records\McpServer::class;

    public static function type(): string
    {
        return 'admin-mcp';
    }
}
