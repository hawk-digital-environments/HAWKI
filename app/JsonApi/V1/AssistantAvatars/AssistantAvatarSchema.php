<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantAvatars;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AssistantAvatarSchema extends Schema
{
    public static string $model = AssistantAvatar::class;

    public function __construct(
        Server $server,
        private readonly AssistantRepository $repository,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'assistant-avatars';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name')->sortable(),
            Str::make('icon_css'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),

            BelongsTo::make('assistant')->type('assistants'),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }

    /**
     * Scope the standalone collection to avatars whose owning assistant the
     * caller is allowed to view.
     */
    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request?->user();

        if ($user === null) {
            return $query;
        }

        return $query->whereHas('assistant', fn (Builder $assistantQuery) => $this->repository->filterVisibleForUser($assistantQuery, $user));
    }
}
