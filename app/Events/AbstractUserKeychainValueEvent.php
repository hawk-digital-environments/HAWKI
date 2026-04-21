<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\UserKeychainValue;

abstract readonly class AbstractUserKeychainValueEvent
{
    public function __construct(
        public UserKeychainValue $value
    )
    {
    }
}
