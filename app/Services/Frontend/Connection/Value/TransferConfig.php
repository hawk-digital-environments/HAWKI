<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value;


use App\Utils\JsonSerializableTrait;

readonly class TransferConfig implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string          $baseUrl,
        public WebsocketConfig $websocket,
        public RouteConfig     $routes
    )
    {
    }
}
