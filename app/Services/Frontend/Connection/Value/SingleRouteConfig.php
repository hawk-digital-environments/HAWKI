<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\Assert\Assert;

readonly class SingleRouteConfig implements \JsonSerializable
{
    public function __construct(
        public string $key,
        public string $route,
        public string $method
    )
    {
        Assert::withKeyPrefix(
            static::class,
            static fn() => Assert::isNonEmptyString($key, 'key'),
            static fn() => Assert::isIn($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'method'),
            static fn() => Assert::isNonEmptyString($route, 'route'),
        );
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'route' => $this->route,
            'method' => $this->method,
        ];
    }
}
