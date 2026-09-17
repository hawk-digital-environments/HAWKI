<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantFieldFlags;

use App\Models\Assistants\AssistantFieldFlag;
use App\Services\Admin\Permission;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Resources\Relation;
use LaravelJsonApi\Eloquent\Schema;

/**
 * An admin's flag+comment on one field of an assistant, optionally anchored to
 * a selected excerpt. Readable by whoever may see the assistant's other
 * privileged relationships (creator, org admin, site admin — see
 * AssistantPolicy::viewAssistantFieldFlags); writable only by a site admin
 * (AssistantFieldFlagPolicy).
 */
class AssistantFieldFlagSchema extends Schema
{
    public static string $model = AssistantFieldFlag::class;

    public function __construct(
        Server $server,
        private readonly AssistantRepository $repository,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'assistant-field-flags';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('field'),
            Str::make('excerpt'),
            Str::make('comment'),
            Boolean::make('resolved'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            BelongsTo::make('admin')->type('users')->readOnly(),
            BelongsTo::make('assistant')->type('assistants')
                ->serializeUsing(static function (Relation $relation): void {
                    $relation->withoutSelfLink()->withoutRelatedLink();
                }),
        ];
    }

    public function filters(): array
    {
        return [
            Where::make('assistant_id'),
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

        if ($user->can(Permission::ASSISTANTS_MANAGE->value)) {
            return $query;
        }

        return $query->whereHas('assistant', fn (Builder $assistantQuery) => $this->repository->filterPrivilegedForUser($assistantQuery, $user));
    }
}
