<?php

declare(strict_types=1);

namespace App\JsonApi\V1\McpServers;

use App\Models\Ai\Tools\McpServer;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class McpServerSchema extends Schema
{
    public static string $model = McpServer::class;

    public static function type(): string
    {
        return 'mcp-servers';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('url'),
            Str::make('server_label'),
            Str::make('version'),
            Str::make('protocolVersion', 'protocolVersion'),
            Str::make('description'),
            Str::make('require_approval'),
            Str::make('timeout'),
            Str::make('discovery_timeout'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasMany::make('tools')->type('ai-tools')->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
