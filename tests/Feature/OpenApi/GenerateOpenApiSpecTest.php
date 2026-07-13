<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use App\Services\OpenApi\OpenApiGenerator;
use LaravelJsonApi\Core\Server\Server as BaseServer;
use LaravelJsonApi\Core\Support\AppResolver;
use Tests\Feature\OpenApi\Fixtures\TestingController;
use Tests\Feature\OpenApi\Fixtures\TestServer;
use Tests\TestCase;

#[CoversClass(OpenApiGenerator::class)]
class GenerateOpenApiSpecTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(Server::class, static function ($app) {
            $server = new TestServer(new AppResolver(static fn () => $app), 'v1');
            $app->instance(BaseServer::class, $server);

            return $server;
        });

        $this->registerTestingRoutes();
    }

    public function testEndpointReturnsValidSpec(): void
    {
        $this->generateSpec();

        self::assertSame('3.0.3', $this->spec['openapi']);
        self::assertNotEmpty($this->spec['paths']);
        self::assertNotEmpty($this->spec['components']['schemas']);
    }

    public function testFilterParametersInCollectionPath(): void
    {
        $this->generateSpec();

        $params = $this->spec['paths']['/testing']['get']['parameters'];

        $filterNames = collect($params)
            ->filter(static fn ($p) => str_starts_with($p['name'] ?? '', 'filter['))
            ->pluck('name')
            ->all();

        self::assertContains('filter[name]', $filterNames);
        self::assertContains('filter[status]', $filterNames);
    }

    public function testValidationConstraintsInCreateRequest(): void
    {
        $this->generateSpec();

        $createRequest = $this->spec['components']['schemas']['TestingCreateRequest'];
        $attrs = $createRequest['properties']['data']['properties']['attributes'];

        self::assertContains('name', $attrs['required']);
        self::assertContains('status', $attrs['required']);
        self::assertContains('max_count', $attrs['required']);

        self::assertSame(50, $attrs['properties']['name']['maxLength']);

        self::assertSame(['active', 'inactive'], $attrs['properties']['status']['enum']);

        self::assertSame('integer', $attrs['properties']['max_count']['type']);
        self::assertSame(0, $attrs['properties']['max_count']['minimum']);
        self::assertSame(100, $attrs['properties']['max_count']['maximum']);
    }

    public function testReadonlyFieldExcludedFromWritableSchema(): void
    {
        $this->generateSpec();

        $createAttrs = $this->spec['components']['schemas']['TestingCreateRequest']['properties']['data']['properties']['attributes']['properties'];

        self::assertArrayNotHasKey('created_at', $createAttrs);

        $responseAttrs = $this->spec['components']['schemas']['TestingAttributes']['properties'];

        self::assertArrayHasKey('created_at', $responseAttrs);
    }

    public function testResourceOverridesInResponseExample(): void
    {
        $schemaBuilder = $this->app->make(SchemaBuilder::class);

        $builder = \Mockery::mock(ExampleBuilder::class, [$schemaBuilder])->makePartial();
        $builder->shouldReceive('buildResponseExample')
            ->with(\Mockery::any(), 'testing')
            ->andReturn([
                'data' => [
                    'id' => '1',
                    'type' => 'testing',
                    'attributes' => [
                        'name' => 'Overridden Value',
                        'status' => 'active',
                    ],
                ],
            ]);

        $this->app->instance(ExampleBuilder::class, $builder);

        $this->generateSpec();

        $example = $this->spec['paths']['/testing/{id}']['get']['responses']['200']['content']['application/vnd.api+json']['example'];

        self::assertSame('Overridden Value', $example['data']['attributes']['name']);
        self::assertSame('active', $example['data']['attributes']['status']);
    }

    public function testTypeBasedFallbackInResponseExample(): void
    {
        $this->generateSpec();

        $example = $this->spec['paths']['/testing/{id}']['get']['responses']['200']['content']['application/vnd.api+json']['example'];

        self::assertSame(0, $example['data']['attributes']['max_count']);
        self::assertFalse($example['data']['attributes']['is_active']);
    }

    public function testValidationDerivedRequestExample(): void
    {
        $this->generateSpec();

        $example = $this->spec['paths']['/testing']['post']['requestBody']['content']['application/vnd.api+json']['example'];

        self::assertSame(50, $example['data']['attributes']['max_count']);
    }

    private function generateSpec(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/hawki/v1/openapi.json')
            ->assertOk();

        $this->spec = $response->json();
    }

    private function registerTestingRoutes(): void
    {
        $router = $this->app['router'];

        $router->prefix('api')->group(static function ($router): void {
            $router->get('testing', [TestingController::class, 'index']);
            $router->post('testing', [TestingController::class, 'store']);
            $router->get('testing/{testing}', [TestingController::class, 'show']);
            $router->patch('testing/{testing}', [TestingController::class, 'update']);
            $router->delete('testing/{testing}', [TestingController::class, 'destroy']);
        });
    }
}
