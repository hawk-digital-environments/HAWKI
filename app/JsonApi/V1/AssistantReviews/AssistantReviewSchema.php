<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantReviews;

use App\Models\Assistants\AssistantReview;
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

class AssistantReviewSchema extends Schema
{
    public static string $model = AssistantReview::class;

    public function __construct(
        Server $server,
        private readonly AssistantRepository $assistantRepository,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'assistant-reviews';
    }

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

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request?->user();

        if (null === $user) {
            return $query;
        }

        return $query->whereHas('assistant', fn (Builder $assistantQuery) =>
            $this->assistantRepository->filterPrivilegedForUser($assistantQuery, $user));
    }

}
