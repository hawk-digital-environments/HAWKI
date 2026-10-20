<?php

declare(strict_types=1);

namespace App\JsonApi\V1\McpServers;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Values\McpServerTimeouts;
use App\Services\System\JsonApi\ValueSerializer;
use App\Services\Users\UserCondition;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class McpServerSchema extends Schema
{
    public static string $model = McpServer::class;

    public static function type(): string
    {
        return 'mcp-servers';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('type')
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('url')
                // @todo is this needed for creation of assistants?
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('server_label'),
            Str::make('status')->readOnly(),
            Str::make('version')
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('protocol_version')
                ->hidden(UserCondition::isNonAdmin(...)),
            Str::make('description'),
            Str::make('require_approval'),
            ArrayHash::make('timeouts')
                ->hidden(UserCondition::isNonAdmin(...))
                ->serializeUsing(fn(McpServerTimeouts $timeouts) => $timeouts->toArray()),
            Str::make('api_key')
                ->hidden(UserCondition::isNonAdmin(...))
                ->serializeUsing(ValueSerializer::apiKey(...)),
            Boolean::make('added_by_file')
                ->hidden(UserCondition::isNonAdmin(...))
                ->readOnly(),
            ArrayHash::make('additional_config')
                ->hidden(UserCondition::isNonAdmin(...)),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasMany::make('tools')->type('ai-tools')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            Where::make('status')
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
