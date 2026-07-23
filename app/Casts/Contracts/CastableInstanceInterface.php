<?php
declare(strict_types=1);


namespace App\Casts\Contracts;

use App\Casts\AsInstance;

/**
 * Works in combination with {@see AsInstance} to allow an object
 * to be cast from an Eloquent attribute.
 *
 * The database value is expected to be a JSON string that can be converted to an array,
 * which is then passed to the `fromArray` method to create an instance of the class implementing this interface.
 */
interface CastableInstanceInterface
{
    public static function fromArray(array $data): static;

    public function toArray(): array;
}
