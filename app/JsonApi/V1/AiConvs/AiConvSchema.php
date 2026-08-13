<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvs;

use App\Models\AiConv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiConvSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     */
    public static string $model = AiConv::class;

    /**
     * Get the resource fields.
     *
     * Only the conversation metadata is exposed here; the encrypted message
     * bodies stay behind the single-conversation endpoint so listings never
     * download chat histories they do not display.
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name', 'conv_name'),
            Str::make('slug'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    /**
     * Get the resource filters.
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Private conversations belong to exactly one user, so the index only
     * ever returns the conversations of the requesting user.
     */
    public function indexQuery(?Request $request, Builder $query): Builder
    {
        return $query
            ->where('user_id', $request?->user()?->id)
            ->latest('updated_at');
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
