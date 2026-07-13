<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use App\Http\Controllers\OpenApiSpecController;
use App\JsonApi\V1\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\Core\Server\Server as BaseServer;
use LaravelJsonApi\Core\Support\AppResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\OpenApi\Fixtures\TestServer;
use Tests\TestCase;

#[CoversClass(OpenApiSpecController::class)]
class OpenApiSpecIsPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(Server::class, static function ($app) {
            $server = new TestServer(new AppResolver(static fn () => $app), 'v1');
            $app->instance(BaseServer::class, $server);

            return $server;
        });
    }

    public function testItIsAccessibleWithoutAuthentication(): void
    {
        $this
            ->getJson('/api/hawki/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3');
    }
}
