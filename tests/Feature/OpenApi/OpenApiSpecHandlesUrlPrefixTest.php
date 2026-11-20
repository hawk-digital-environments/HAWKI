<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\PathBuilder;
use LaravelJsonApi\Core\Server\Server as BaseServer;
use LaravelJsonApi\Core\Support\AppResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\OpenApi\Fixtures\TestServer;
use Tests\Feature\OpenApi\Fixtures\TestingController;
use Tests\TestCase;

#[CoversClass(PathBuilder::class)]
class OpenApiSpecHandlesUrlPrefixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(Server::class, static function ($app) {
            $server = new TestServer(new AppResolver(static fn () => $app), 'v1');
            $app->instance(BaseServer::class, $server);

            return $server;
        });

        $this->registerPrefixedRoutes();
    }

    public function testItIncludesRoutesUnderTheServerUrlPrefix(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/hawki/v1/openapi.json')
            ->assertOk();

        $paths = $response->json('paths');

        self::assertIsArray($paths);
        self::assertNotEmpty($paths);

        $expectedPath = Server::BASE_URL_PREFIX . '/widgets';

        self::assertArrayHasKey($expectedPath, $paths);
        self::assertArrayHasKey('get', $paths[$expectedPath]);
    }

    public function testItPrefixesSingleResourceRoutesAndKeepsIdParameter(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/hawki/v1/openapi.json')
            ->assertOk();

        $expectedPath = Server::BASE_URL_PREFIX . '/widgets/{id}';

        self::assertArrayHasKey($expectedPath, $response->json('paths'));
    }

    private function registerPrefixedRoutes(): void
    {
        $router = $this->app['router'];

        $router->prefix('api' . Server::BASE_URL_PREFIX)->group(static function ($router): void {
            $router->get('widgets', [TestingController::class, 'index']);
            $router->get('widgets/{widget}', [TestingController::class, 'show']);
        });
    }
}
