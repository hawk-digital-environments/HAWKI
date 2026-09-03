<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserFavorites;

use App\Models\UserFavoriteValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Schema of the `user-favorites` JSON:API resource — a row-per-favorite Eloquent
 * resource over {@see UserFavoriteValue}.
 *
 * Deliberately not the settings-style namespace aggregate: favorites are
 * set-membership data, so REST `store`/`destroy` is the natural toggle transport
 * and each row is independently addressable (DELETE by id).
 *
 * Access control: `authorizable()` is false per HAWKI convention — the middleware
 * layer (UserContext) gates authentication. Note that the model's `'access'`
 * contextual scope does **not** apply to queries the JSON:API layer builds itself
 * (verified: route binding and `$store->delete()` bypass it), so user scoping is
 * done explicitly in {@see indexQuery()} and the controller's `deleting` hook.
 */
class UserFavoriteSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     */
    public static string $model = UserFavoriteValue::class;

    /**
     * Get the resource fields.
     *
     * The model column `type` is exposed as `item_type` on the wire: JSON:API
     * reserves the member names `type` and `id`, so a wire attribute literally
     * named `type` is rejected by the spec validator before reaching validation.
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('namespace'),
            Str::make('item_type', 'type'),
            Str::make('identifier'),
        ];
    }

    /**
     * Get the resource filters.
     *
     * `item_type` filters the model's `type` column.
     */
    public function filters(): array
    {
        return [
            Where::make('namespace'),
            Where::make('item_type', 'type'),
            Where::make('identifier'),
        ];
    }

    /**
     * The index only ever returns the requesting user's favorites.
     *
     * The model's `'access'` contextual scope is NOT applied here — the JSON:API
     * layer builds its queries directly on the model, outside the repository's
     * scope activation — so the user filter is explicit (same approach as
     * {@see \App\JsonApi\V1\AiConvs\AiConvSchema::indexQuery()}).
     */
    public function indexQuery(?Request $request, Builder $query): Builder
    {
        return $query->where('user_id', $request?->user()?->id);
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }

    public function authorizable(): bool
    {
        // HAWKI handles auth at the middleware layer (UserContext/UsageContext).
        // Row-level user scoping is NOT the model's 'access' contextual scope here —
        // the JSON:API layer builds its queries outside the scope activation — but
        // the explicit user_id filter in indexQuery() plus the controller's
        // deleting-ownership hook (see this class's docblock).
        return false;
    }
}
