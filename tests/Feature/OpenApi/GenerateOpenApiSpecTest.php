<?php

namespace Tests\Feature\OpenApi;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use App\Services\OpenApi\OpenApiGenerator;
use LaravelJsonApi\Core\Server\Server as BaseServer;
use LaravelJsonApi\Core\Support\AppResolver;
use Mockery;
use Tests\Feature\OpenApi\Fixtures\TestingController;
use Tests\Feature\OpenApi\Fixtures\TestServer;
use Tests\TestCase;

class GenerateOpenApiSpecTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(Server::class, function ($app) {
            $server = new TestServer(new AppResolver(fn () => $app), 'v1');
            $app->instance(BaseServer::class, $server);

            return $server;
        });

        $this->registerTestingRoutes();
    }

    public function test_command_generates_valid_spec(): void
    {
        $this->artisan('openapi:generate', ['--no-examples' => true])
            ->assertSuccessful();

        $this->spec = $this->readGeneratedSpec();

        $this->assertSame('3.0.3', $this->spec['openapi']);
        $this->assertNotEmpty($this->spec['paths']);
        $this->assertNotEmpty($this->spec['components']['schemas']);
    }

    public function test_filter_parameters_in_collection_path(): void
    {
        $this->generateSpec();

        $params = $this->spec['paths']['/testing']['get']['parameters'];

        $filterNames = collect($params)
            ->filter(fn ($p) => str_starts_with($p['name'] ?? '', 'filter['))
            ->pluck('name')
            ->all();

        $this->assertContains('filter[name]', $filterNames);
        $this->assertContains('filter[status]', $filterNames);
    }

    public function test_validation_constraints_in_create_request(): void
    {
        $this->generateSpec();

        $createRequest = $this->spec['components']['schemas']['TestingCreateRequest'];
        $attrs = $createRequest['properties']['data']['properties']['attributes'];

        $this->assertContains('name', $attrs['required']);
        $this->assertContains('status', $attrs['required']);
        $this->assertContains('max_count', $attrs['required']);

        $this->assertSame(50, $attrs['properties']['name']['maxLength']);

        $this->assertSame(['active', 'inactive'], $attrs['properties']['status']['enum']);

        $this->assertSame('integer', $attrs['properties']['max_count']['type']);
        $this->assertSame(0, $attrs['properties']['max_count']['minimum']);
        $this->assertSame(100, $attrs['properties']['max_count']['maximum']);
    }

    public function test_readonly_field_excluded_from_writable_schema(): void
    {
        $this->generateSpec();

        $createAttrs = $this->spec['components']['schemas']['TestingCreateRequest']['properties']['data']['properties']['attributes']['properties'];

        $this->assertArrayNotHasKey('created_at', $createAttrs);

        $responseAttrs = $this->spec['components']['schemas']['TestingAttributes']['properties'];

        $this->assertArrayHasKey('created_at', $responseAttrs);
    }

    public function test_resource_overrides_in_response_example(): void
    {
        $schemaBuilder = $this->app->make(SchemaBuilder::class);

        $builder = Mockery::mock(ExampleBuilder::class, [$schemaBuilder])->makePartial();
        $builder->shouldReceive('buildResponseExample')
            ->with(Mockery::any(), 'testing')
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

        $this->assertSame('Overridden Value', $example['data']['attributes']['name']);
        $this->assertSame('active', $example['data']['attributes']['status']);
    }

    public function test_type_based_fallback_in_response_example(): void
    {
        $this->generateSpec();

        $example = $this->spec['paths']['/testing/{id}']['get']['responses']['200']['content']['application/vnd.api+json']['example'];

        $this->assertSame(0, $example['data']['attributes']['max_count']);
        $this->assertFalse($example['data']['attributes']['is_active']);
    }

    public function test_validation_derived_request_example(): void
    {
        $this->generateSpec();

        $example = $this->spec['paths']['/testing']['post']['requestBody']['content']['application/vnd.api+json']['example'];

        $this->assertSame(50, $example['data']['attributes']['max_count']);
    }

    private function generateSpec(): void
    {
        $generator = $this->app->make(OpenApiGenerator::class);
        $spec = $generator->generate(false);

        $this->spec = $spec;
    }

    private function registerTestingRoutes(): void
    {
        $router = $this->app['router'];

        $router->prefix('api')->group(function ($router) {
            $router->get('testing', [TestingController::class, 'index']);
            $router->post('testing', [TestingController::class, 'store']);
            $router->get('testing/{testing}', [TestingController::class, 'show']);
            $router->patch('testing/{testing}', [TestingController::class, 'update']);
            $router->delete('testing/{testing}', [TestingController::class, 'destroy']);
        });
    }

    private function readGeneratedSpec(): array
    {
        $path = public_path('docs/openapi.json');
        $json = file_get_contents($path);

        return json_decode($json, true);
    }
}
