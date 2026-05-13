<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Reviews;

use App\Models\Assistants\Review;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class ReviewSchema extends Schema
{
    public static string $model = Review::class;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('status'),
            Str::make('reason'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),

            BelongsTo::make('assistant')->readOnly(),
        ];
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
