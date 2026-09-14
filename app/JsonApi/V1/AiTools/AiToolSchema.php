<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiTools;

use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Container\ServiceLocatorTrait;
use App\Services\Users\UserCondition;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiToolSchema extends Schema
{
    use ServiceLocatorTrait;

    public static string $model = AiTool::class;

    public static function type(): string
    {
        return 'ai-tools';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Boolean::make('active')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            Str::make('type')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            Str::make('name'),
            Str::make('class_name')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            Str::make('mcp_name')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            ArrayHash::make('mcp_config')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            Str::make('description'),
            Str::make('server_capability_key', 'capability')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage'))
                ->readOnly(),
            Str::make('mapped_capability_key')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage')),
            Str::make('capability_key', 'effective_capability')
                ->extractUsing(fn(AiTool $tool) => $tool->getEffectiveCapability()),
            Str::make('status')
                ->extractUsing(function (AiTool $tool): string {
                    if ($tool->type === ToolType::FUNCTION) {
                        return OnlineStatus::ONLINE->value;
                    }
                    return $tool->server->status->value ?? OnlineStatus::UNKNOWN->value;
                }),
            Boolean::make('added_by_file')
                ->hidden(fn($request) => UserCondition::cannot($request, 'mcp.manage'))
                ->readOnly(),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('server')->type('mcp-servers')->readOnly(),
            BelongsToMany::make('models')->type('ai-models')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            new AiToolStatusFilter(),
            new AiToolAssignedToModelFilter()
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
