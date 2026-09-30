<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantReviewLogs;

use App\Models\Assistants\AssistantReviewLog;
use App\Services\Admin\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

/**
 * The Publishing Center's "administrative log": one row per approve/deny/block
 * decision, admin-only (see AssistantReviewLogPolicy) — unlike the rest of the
 * review data, creators never see this, so it is scoped strictly to
 * `assistants.manage` here rather than the usual creator/org-admin tier.
 */
class AssistantReviewLogSchema extends Schema
{
    public static string $model = AssistantReviewLog::class;

    public static function type(): string
    {
        return 'assistant-review-logs';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('action')->readOnly(),
            Str::make('reason')->readOnly(),
            DateTime::make('created_at')->sortable()->readOnly(),
            BelongsTo::make('admin')->type('users')->readOnly(),
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

        abort_unless(null !== $user && $user->can(Permission::ASSISTANTS_MANAGE->value), 403);

        return $query->orderByDesc('created_at');
    }
}
