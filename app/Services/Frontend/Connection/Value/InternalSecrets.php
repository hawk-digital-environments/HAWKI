<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class InternalSecrets implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string $csrfToken,
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->csrfToken, 'csrfToken'),
        );
    }
}
