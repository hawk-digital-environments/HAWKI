<?php

namespace App\JsonApi\V1\Rooms;

use App\Models\Room;
use App\Services\Storage\Values\StoredFileIdentifier;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class RoomSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Room::class;

    protected int $maxDepth = 3;

//    protected ?array $defaultPagination = ['size' => 100];


    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
            Str::make('avatar')->extractUsing(function (Room $user) {
                $identifier = StoredFileIdentifier::tryFromRoomAvatar($user);
                return $identifier ? (string)$identifier : null;
            }),
            Str::make('slug'),
            Str::make('system_prompt'),
            Str::make('room_description'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            HasMany::make('members')->type('users'),
            HasMany::make('messages')->type('room-messages'),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
            // WhereIdIn::make($this)
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
