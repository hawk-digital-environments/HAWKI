<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiProvider;
use App\Services\Admin\ResourceCatalog;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Contracts\Server\Repository;
use LaravelJsonApi\Laravel\Routing\Route as JsonApiRoute;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AdminApiRoutingTest extends TestCase
{
    public function testAdminRoutesRequireAuthenticationBeforeResolvingRecords(): void
    {
        foreach (array_keys(ResourceCatalog::SECTIONS) as $section) {
            $this->getJson('/api/hawki/v1/admin-' . $section)->assertUnauthorized();
        }

        $this->patchJson('/api/hawki/v1/admin-roles/1')->assertUnauthorized();
        $this->postJson('/api/hawki/v1/admin-providers/1/actions/test')->assertUnauthorized();
        $this->postJson('/api/hawki/v1/admin-health/job-id/actions/retry-job')->assertUnauthorized();
    }

    public function testAdminSchemasResolveTheirOwnRecordsWithoutReplacingPublicModels(): void
    {
        $server = app(Repository::class)->server('v1');
        $version = str_repeat('a', 64);

        foreach (array_keys(ResourceCatalog::SECTIONS) as $section) {
            $type = 'admin-' . $section;
            $schema = $server->schemas()->schemaFor($type);
            $class = $schema->model();
            $record = new $class(['id' => '42', 'type' => 'example', '_version' => $version]);
            $resource = $server->resources()->create($record);

            self::assertSame($type, $resource->type());
            self::assertSame('42', $resource->id());
            self::assertSame(['kind' => 'example'], iterator_to_array($resource->attributes(request())));
            self::assertSame(['version' => $version], $resource->meta(request()));
            self::assertNull($resource->selfUrl());
        }

        self::assertSame('ai-providers', $server->schemas()->schemaForModel(AiProvider::class)->type());
    }

    public function testAdminResourcesAndActionsUseTheV1JsonApiServer(): void
    {
        $types = [];

        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/hawki/v1/admin-')) {
                continue;
            }

            $type = explode('/', $route->uri())[3];
            self::assertSame($type, $route->defaults[JsonApiRoute::RESOURCE_TYPE] ?? null, $route->uri());
            self::assertContains('jsonapi:v1', $route->gatherMiddleware());
            self::assertContains('auth:sanctum', $route->gatherMiddleware());
            self::assertContains('throttle:120,1', $route->gatherMiddleware());
            self::assertContains(ConvertEmptyStringsToNull::class, $route->excludedMiddleware());
            self::assertStringStartsWith('v1.' . $type . '.', $route->getName());
            $types[] = mb_substr($type, 6);
        }

        self::assertEqualsCanonicalizing(array_keys(ResourceCatalog::SECTIONS), array_unique($types));
    }
}
