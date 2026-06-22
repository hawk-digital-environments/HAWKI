<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantAvatarUrlResolver;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Filters\WhereHas;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AssistantSchema extends Schema
{
    public static string $model = Assistant::class;

    public function __construct(
        Server $server,
        private AssistantAvatarUrlResolver $urlResolver,
        private AssistantRepository $repository,
    ) {
        parent::__construct($server);
    }

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
            Str::make('model'),
            Number::make('max_tokens'),
            Number::make('temp'),
            Number::make('top_p'),
            Str::make('avatar_id'),
            Str::make('avatar_url')->readOnly()->extractUsing(
                fn (Assistant $model) => $this->urlResolver->forUuid($model->avatar_id),
            ),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            Str::make('version_text')->hidden(),
            Boolean::make('is_favorite')->readOnly(),

            BelongsTo::make('category')->type('assistant-categories'),
            HasMany::make('setting_values', 'settingValues')->type('assistant-setting-values')->readOnly(),
            HasMany::make('user_prompts', 'user_prompts'),
            BelongsToMany::make('ai_tools', 'ai_tools'),
            BelongsToMany::make('tags', 'tags'),
            BelongsTo::make('creator', 'creator')->type('users')->readOnly(),
            BelongsTo::make('remix_creator', 'remix_creator')->type('users')->readOnly(),
            BelongsTo::make('remixed_assistant', 'remixed_assistant')->type('assistants')->readOnly(),
            HasMany::make('versions', 'versions')->readOnly(),
            BelongsTo::make('organization')->readOnly(),
            HasOne::make('review', 'review')->type('assistant-reviews')->readOnly(),
            HasMany::make('feedback', 'feedback')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            WhereHas::make($this, 'category'),
            AssistantNameFilter::make(),
            AssistantFavoriteFilter::make(),
            Where::make('release_stage'),
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

        return $this->repository
            ->filterVisibleForUser($query, $user)
            ->withCount(['favoritedByUsers as is_favorite' => fn ($q) => $q->where('user_id', $user->id)]);
    }
}
