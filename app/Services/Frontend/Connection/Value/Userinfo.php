<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class Userinfo implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public int    $id,
        public string $username,
        public string $email,
        public string $hash
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isPositiveInteger($this->id, 'id'),
            fn() => Assert::isNonEmptyString($this->username, 'username'),
            fn() => Assert::isNonEmptyString($this->email, 'email'),
            fn() => Assert::isNonEmptyString($this->hash, 'hash'),
        );
    }
}
