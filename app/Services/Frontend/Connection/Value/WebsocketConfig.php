<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;
use App\Utils\JsonSerializableTrait;

readonly class WebsocketConfig implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public int $port;
    
    public function __construct(
        public string $key,
        public string $host,
        string|int    $port,
        public bool   $forceTLS,
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->key, 'key'),
            fn() => Assert::isNonEmptyString($this->host, 'host'),
            static fn() => Assert::isNonEmptyString($port, 'port'),
            static fn() => Assert::isPositiveInteger((int)$port, 'port'),
        );
        
        $this->port = (int)$port;
    }
}
