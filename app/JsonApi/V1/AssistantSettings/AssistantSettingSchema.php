<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantSettings;

use App\Models\Assistants\AssistantSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\WhereIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AssistantSettingSchema extends Schema
{
    public static string $model = AssistantSetting::class;

    public static function type(): string
    {
        return 'assistant-settings';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('key')->sortable(),
            Str::make('label'),
            Str::make('description'),
            Str::make('ui_type'),
            Str::make('ui_options'),
            Str::make('prompt_template'),
            Str::make('default_value'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            HasMany::make('values')->type('assistant-setting-values')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            WhereIn::make('key')->delimiter(','),
        ];
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        return $query->orderBy('key');
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
