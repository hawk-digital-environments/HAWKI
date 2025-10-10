<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


readonly class RouteConfig implements \JsonSerializable
{
    public array $routes;
    
    public function __construct(
        SingleRouteConfig ...$routes
    )
    {
        $this->routes = $routes;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $result = [];
        foreach ($this->routes as $route) {
            $result[$route->key] = $route->jsonSerialize();
        }
        return $result;
    }
}
