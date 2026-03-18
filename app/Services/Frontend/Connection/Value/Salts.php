<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class Salts implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string $userdata,
        public string $invitation,
        public string $ai,
        public string $passkey,
        public string $backup
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->userdata, 'userdata'),
            fn() => Assert::isNonEmptyString($this->invitation, 'invitation'),
            fn() => Assert::isNonEmptyString($this->ai, 'ai'),
            fn() => Assert::isNonEmptyString($this->passkey, 'passkey'),
            fn() => Assert::isNonEmptyString($this->backup, 'backup'),
        );
    }
}
