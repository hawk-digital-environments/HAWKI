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
        public string      $key,
        public string      $host,
        string|int         $port,
        public bool        $forceTLS,
        public string|null $path = null,
    )
    {
        Assert::withKeyPrefix(
            static::class,
            fn() => Assert::isNonEmptyString($this->key, 'key'),
            fn() => Assert::isNonEmptyString($this->host, 'host'),
            static fn() => Assert::isPositiveInteger((int)$port, 'port'),
            fn() => Assert::isNonEmptyStringOrNull($this->path, 'path'),
        );

        $this->port = (int)$port;
    }
}
