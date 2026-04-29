<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Categories;

use App\Models\Assistants\Category;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\WhereIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CategorySchema extends Schema
{
    public static string $model = Category::class;

    public static function type(): string
    {
        return 'assistant-categories';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('text')->sortable(),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),

            HasMany::make('assistants')->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [
            WhereIn::make('text')->delimiter(','),
        ];
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        return $query->orderBy('text');
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
