<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiTools;

use App\Models\Ai\Tools\AiTool;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AiToolSchema extends Schema
{
    public static string $model = AiTool::class;

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
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
