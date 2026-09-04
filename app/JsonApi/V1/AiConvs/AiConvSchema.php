<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiConvs;

use App\Models\AiConv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
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
     * Conversation responses may include both messages and their authors.
     */
    protected int $maxDepth = 2;

    /**
     * Get the resource fields.
     *
     * The slug doubles as the resource id because the frontend routes address
     * conversations by slug, never by their numeric database id.
     *
     * Only the conversation metadata is exposed by default; the encrypted
     * message bodies are only serialized when a single conversation is
     * requested with `?include=messages`, so listings never download chat
     * histories they do not display.
     */
    public function fields(): array
    {
        return [
            ID::make('slug')->matchAs('[a-zA-Z0-9-]+'),
            Str::make('name', 'conv_name'),
            Str::make('slug')->readOnly(),
            Str::make('system_prompt'),
            Str::make('assistant_handle'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
            HasMany::make('messages')->type('ai-conv-messages')->readOnly(),
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
     *
     * @todo Exchange this method for a contextual scope (equivalent of \App\Models\Scopes\RoomMemberAccessScope, see _documentation/500-Backend/200-Concepts/140-Contextual-Scopes.md) when merging chats and group chats.
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
