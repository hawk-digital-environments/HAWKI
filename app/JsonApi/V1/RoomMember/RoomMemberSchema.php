<?php

namespace App\JsonApi\V1\RoomMember;

use App\Models\Member;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\WhereIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class RoomMemberSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Member::class;

    protected ?array $defaultPagination = ['size' => 100];

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Number::make('user_id'),
            Number::make('room_id'),
            Str::make('role'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
            HasOne::make('user')->readOnly(),
            HasOne::make('room')->readOnly(),
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
            WhereIn::make('room', 'room_id')
        ];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
