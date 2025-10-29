<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value\Builder;


use App\Services\Frontend\Connection\Value\RouteConfig;
use App\Services\Frontend\Connection\Value\SingleRouteConfig;
use Illuminate\Routing\RouteCollectionInterface;

class RouteConfigBuilder
{
    /**
     * @var SingleRouteConfig[]
     */
    private array $internalRoutes = [];
    /**
     * @var SingleRouteConfig[]
     */
    private array $extAppRoutes = [];
    
    public function __construct(
        private readonly RouteCollectionInterface $routes
    )
    {
    }
    
    public function addRoute(
        string  $key,
        string  $routeNameInternal,
        string  $routeNameExtApp,
        ?string $methodInternal = null,
        ?string $methodExtApp = null,
    ): self
    {
        $this->addToStack($this->internalRoutes, $key, $routeNameInternal, $methodInternal);
        $this->addToStack($this->extAppRoutes, $key, $routeNameExtApp, $methodExtApp);
        return $this;
    }
    
    public function buildInternalRouteConfig(): RouteConfig
    {
        return new RouteConfig(...$this->internalRoutes);
    }
    
    public function buildExtAppRouteConfig(): RouteConfig
    {
        return new RouteConfig(...$this->extAppRoutes);
    }
    
    private function addToStack(
        array   &$stack,
        string  $key,
        string  $routeName,
        ?string $method = null,
    ): void
    {
        $route = $this->routes->getByName($routeName);
        if ($route === null) {
            throw new \InvalidArgumentException("Route with name '$routeName' not found for '$key'");
        }
        
        $method = $method !== null ? strtoupper($method) : null;
        $methods = $route->methods();
        if ($method !== null && !in_array($method, $methods, true)) {
            throw new \InvalidArgumentException("Method '$method' not allowed for route '$routeName' for '$key'");
        }
        
        $stack[] = new SingleRouteConfig(
            key: $key,
            route: $route->uri(),
            method: $method ?? ($methods === ['HEAD'] ? 'GET' : $methods[0]),
        );
    }
    
}
