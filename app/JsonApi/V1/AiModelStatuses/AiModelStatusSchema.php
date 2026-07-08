<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiModelStatuses;

use App\Models\Ai\AiModelStatus;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AiModelStatusSchema extends Schema
{
    public static string $model = AiModelStatus::class;

    public static function type(): string
    {
        return 'ai-model-statuses';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make('model_id'),
            Str::make('status'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
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

    public function pagination(): ?\LaravelJsonApi\Eloquent\Pagination\PagePagination
    {
        return null;
    }
}
