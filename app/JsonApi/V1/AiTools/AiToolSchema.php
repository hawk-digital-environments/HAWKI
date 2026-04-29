<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiTools;

use App\Models\Ai\Tools\AiTool;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiToolSchema extends Schema
{
    public static string $model = AiTool::class;

    public static function type(): string
    {
        return 'ai-tools';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('type'),
            Str::make('name'),
            Str::make('class_name'),
            Str::make('description'),
            Str::make('capability'),
            Str::make('status'),
            Boolean::make('active'),
            ArrayHash::make('inputSchema'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('server')->type('mcp-servers')->readOnly(),
            BelongsToMany::make('models')->type('ai-models')->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [
            Where::make('text')
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
