<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Policies\AssistantPolicy;
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
use LaravelJsonApi\Eloquent\Filters\WhereIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\QueryBuilder\ModelLoader;
use LaravelJsonApi\Eloquent\Schema;

class AssistantSchema extends Schema
{
    public static string $model = Assistant::class;
    protected int $maxDepth = 2;

    public function __construct(
        Server $server,
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
            Str::make('release_stage')->readOnly(),
            Str::make('requested_release_stage')->readOnly(),
            Str::make('model'),
            Number::make('max_tokens'),
            Number::make('temp'),
            Number::make('top_p'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            Boolean::make('is_favorite')->readOnly(),
            BelongsTo::make('assistant_category', 'assistantCategory')->type('assistant-categories'),
            HasOne::make('assistant_avatar', 'assistantAvatar')->type('assistant-avatars')->readOnly(),
            HasMany::make('assistant_setting_values', 'settingValues')->type('assistant-setting-values')->readOnly(),
            HasMany::make('assistant_user_prompts', 'assistantUserPrompts')->type('assistant-user-prompts')->readOnly(),
            BelongsToMany::make('ai_tools', 'ai_tools'),
            BelongsToMany::make('assistant_tags', 'assistantTags')->type('assistant-tags'),
            BelongsTo::make('creator', 'creator')->type('users')->readOnly(),
            BelongsTo::make('remix_creator', 'remix_creator')->type('users')->readOnly(),
            BelongsTo::make('remixed_assistant', 'remixed_assistant')->type('assistants')->readOnly(),
            HasMany::make('assistant_versions', 'assistantVersions')->type('assistant-versions')->readOnly(),
            BelongsTo::make('organization')->type('organizations')->readOnly(),
            HasOne::make('assistant_review', 'assistantReview')->type('assistant-reviews')->readOnly(),
            HasMany::make('assistant_feedback', 'assistantFeedback')->type('assistant-feedback')->readOnly(),
            BelongsToMany::make('shared_users', 'sharedUsers')->type('users'),
            HasMany::make('attachments')->type('attachments')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            WhereHas::make($this, 'assistant_category'),
            AssistantNameFilter::make(),
            AssistantFavoriteFilter::make(),
            WhereIn::make('release_stage')->delimiter(','),
            Where::make('handle')->singular(),
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request?->user();

        if (null === $user) {
            return $query;
        }

        $query = $this->repository
            ->filterVisibleForUser($query, $user)
            ->withCount(['favoritedByUsers as is_favorite' => static fn ($q) => $q->where('user_id', $user->id)]);

        // When a client requests a sensitive relationship via include, narrow
        // the collection to assistants the user is actually allowed to read
        // that relationship for, so the data is not leaked for assistants the
        // user can only view at the public tier.
        $requested = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(static fn (string $path) => explode('.', $path)[0]);

        if ($requested->intersect(AssistantPolicy::PRIVILEGED_RELATIONSHIPS)->isNotEmpty()) {
            return $this->repository->filterPrivilegedForUser($query, $user);
        }

        return $query;
    }

    /**
     * Hydrate the per-user `is_favorite` flag on every model the framework
     * serialises through the single-resource / store / update / related paths.
     *
     * The framework's `QueryOne`, `ModelHydrator` and relation hydrators all
     * route through here, so loading the count here covers show, store, update,
     * and all custom controller actions that re-fetch via `queryOne`. The index
     * path is unaffected: `QueryAll` never calls `loaderFor`, it uses
     * {@see indexQuery()} which already loads the count in batch via withCount.
     *
     * Note: per-model loadCount means an N+1 when Assistants are surfaced as
     * a ToMany include from another schema (only `assistant-categories` does
     * this today); accepted given the rarity of that path.
     *
     * @param mixed $modelOrModels
     */
    public function loaderFor($modelOrModels): ModelLoader
    {
        $loader = parent::loaderFor($modelOrModels);

        $user = app(Request::class)->user();

        if (null !== $user) {
            $modelOrModels->loadCount([
                'favoritedByUsers as is_favorite' => static fn ($q) => $q->where('user_id', $user->id),
            ]);
        }

        return $loader;
    }
}
