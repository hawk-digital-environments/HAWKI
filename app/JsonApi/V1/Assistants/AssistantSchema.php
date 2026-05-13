<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Repositories\AssistantRepository;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class AssistantSchema extends Schema
{
    public static string $model = Assistant::class;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
            Str::make('handle'),
            Str::make('system_prompt'),
            Str::make('greeting'),
            Str::make('description'),
            Str::make('detail_description'),
            Boolean::make('allow_remix'),
            Boolean::make('allow_model_select'),
            Str::make('release_stage'),
            Str::make('formality'),
            Str::make('model'),
            Number::make('model_length'),
            Number::make('model_temp'),
            Number::make('model_top_p'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            Str::make('version_text')->hidden(),

            BelongsTo::make('language'),
            BelongsTo::make('category'),
            HasMany::make('user_prompts', 'user_prompts'),
            BelongsToMany::make('ai_tools', 'ai_tools'),
            BelongsToMany::make('tags', 'tags'),
            BelongsTo::make('creator', 'creator')->type('users')->readOnly(),
            BelongsTo::make('remix_creator', 'remix_creator')->type('users')->readOnly(),
            BelongsTo::make('remixed_assistant', 'remixed_assistant')->type('assistants')->readOnly(),
            HasMany::make('versions', 'versions')->readOnly(),
            BelongsTo::make('organization')->readOnly(),
            HasOne::make('review', 'review')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            CategoryFilter::make(),
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request?->user();

        if ($user === null) {
            return $query;
        }

        return app(AssistantRepository::class)->filterVisibleForUser($query, $user);
    }
}
