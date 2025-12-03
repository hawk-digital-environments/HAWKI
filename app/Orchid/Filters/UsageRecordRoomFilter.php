<?php

namespace App\Orchid\Filters;

use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class UsageRecordRoomFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Room';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['room_id'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $roomId = $this->request->get('room_id');

        if (empty($roomId)) {
            return $builder;
        }

        return $builder->where('room_id', $roomId);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('room_id')
                ->fromModel(Room::class, 'room_name')
                ->empty('All Rooms')
                ->value($this->request->get('room_id'))
                ->title($this->name()),
        ];
    }
}
