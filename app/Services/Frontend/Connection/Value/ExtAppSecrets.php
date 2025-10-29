<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class ExtAppSecrets implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string $passkey,
        public string $apiToken,
        public string $privateKey,
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->passkey, 'passkey'),
            fn() => Assert::isNonEmptyString($this->apiToken, 'apiToken'),
            fn() => Assert::isNonEmptyString($this->privateKey, 'privateKey'),
        );
    }
}
