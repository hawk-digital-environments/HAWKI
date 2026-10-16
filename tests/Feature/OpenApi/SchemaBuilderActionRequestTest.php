<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use App\Http\Requests\Api\V1\StoreAiConvAttachmentRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Services\OpenApi\Builders\SchemaBuilder;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Regression tests for action FormRequests whose rules() declare
 * method-injected dependencies: the spec generator must invoke rules()
 * through the container, not bare (a bare call fatals on the required
 * parameter and 500s the whole openapi.json endpoint).
 */
#[CoversClass(SchemaBuilder::class)]
class SchemaBuilderActionRequestTest extends TestCase
{
    public function testItConstructs(): void
    {
        static::assertInstanceOf(SchemaBuilder::class, $this->app->make(SchemaBuilder::class));
    }

    public function testItResolvesMethodInjectedRulesDependencies(): void
    {
        $this->mock(AvatarStorageService::class)
            ->shouldReceive('getMaxFileSize')->once()->andReturn(2048)
            ->shouldReceive('getAllowedMimeTypes')->once()->andReturn(['image/png']);

        $schema = $this->app->make(SchemaBuilder::class)
            ->buildActionRequestSchema(UploadAvatarRequest::class);

        static::assertSame(['image' => ['type' => 'string']], $schema['properties']);
    }

    public function testItResolvesMethodInjectedRulesDependenciesForAttachmentUploads(): void
    {
        $this->mock(FileStorageService::class)
            ->shouldReceive('getMaxFileSize')->once()->andReturn(4096);

        $schema = $this->app->make(SchemaBuilder::class)
            ->buildActionRequestSchema(StoreAiConvAttachmentRequest::class);

        static::assertArrayHasKey('file', $schema['properties']);
    }
}
