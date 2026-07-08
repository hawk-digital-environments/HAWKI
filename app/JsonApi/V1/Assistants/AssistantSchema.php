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
            Str::make('release_stage'),
            Str::make('model'),
            Number::make('max_tokens'),
            Number::make('temp'),
            Number::make('top_p'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            Boolean::make('is_favorite')->readOnly(),

            BelongsTo::make('category')->type('assistant-categories'),
            HasOne::make('assistant_avatar', 'assistantAvatar')->type('assistant-avatars')->readOnly(),
            HasMany::make('assistant_setting_values', 'settingValues')->type('assistant-setting-values')->readOnly(),
            HasMany::make('assistant_user_prompts', 'user_prompts')->type('assistant-user-prompts')->readOnly(),
            BelongsToMany::make('ai_tools', 'ai_tools'),
            BelongsToMany::make('assistant_tags', 'tags')->type('assistant-tags'),
            BelongsTo::make('creator', 'creator')->type('users')->readOnly(),
            BelongsTo::make('remix_creator', 'remix_creator')->type('users')->readOnly(),
            BelongsTo::make('remixed_assistant', 'remixed_assistant')->type('assistants')->readOnly(),
            HasMany::make('versions', 'versions')->type('versions')->readOnly(),
            BelongsTo::make('organization')->type('organizations')->readOnly(),
            HasOne::make('assistant_review', 'review')->type('assistant-reviews')->readOnly(),
            HasMany::make('assistant_feedback', 'feedback')->type('assistant-feedback')->readOnly(),
            BelongsToMany::make('shared_users', 'sharedUsers')->type('users'),
        ];
    }

    public function filters(): array
    {
        return [
            WhereHas::make($this, 'category'),
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

        if ($user === null) {
            return $query;
        }

        $query = $this->repository
            ->filterVisibleForUser($query, $user)
            ->withCount(['favoritedByUsers as is_favorite' => fn ($q) => $q->where('user_id', $user->id)]);

        // When a client requests a sensitive relationship via include, narrow
        // the collection to assistants the user is actually allowed to read
        // that relationship for, so the data is not leaked for assistants the
        // user can only view at the public tier.
        $requested = collect(explode(',', (string) $request?->query('include', '')))
            ->filter()
            ->map(fn (string $path) => explode('.', $path)[0]);

        if ($requested->intersect(AssistantPolicy::PRIVILEGED_RELATIONSHIPS)->isNotEmpty()) {
            return $this->repository->filterPrivilegedForUser($query, $user);
        }

        if ($requested->intersect(AssistantPolicy::COLLABORATE_RELATIONSHIPS)->isNotEmpty()) {
            return $this->repository->filterCollaborateForUser($query, $user);
        }

        return $query;
    }
}
