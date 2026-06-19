<?php

namespace App\JsonApi\V1\UserKeychainValues;

use App\Models\UserKeychainValue;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class UserKeychainValueSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = UserKeychainValue::class;

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
            Str::make('key'),
            Str::make('value'),
            Str::make('type')
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
}
