<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Chat\Tools;

use App\Services\Ai\Chat\Exceptions\ClientToolExecutionException;
use App\Services\Ai\Chat\Tools\ClientTool;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ClientTool::class)]
class ClientToolTest extends TestCase
{
    private LoggerInterface&Stub $logger;
    private ClientTool $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createStub(LoggerInterface::class);
        $this->sut = new ClientTool(
            definition: new ToolDefinition(
                name: 'read_file',
                description: 'Reads a file from the local filesystem.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Absolute file path.'],
                        'offset' => ['type' => 'integer'],
                    ],
                    'required' => ['path'],
                ],
                requiredParameters: ['path'],
            ),
            logger: $this->logger,
        );
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(ClientTool::class, $this->sut);
    }

    public function testItExposesTheClientChosenNameAndDescription(): void
    {
        self::assertSame('read_file', $this->sut->name());
        self::assertSame('Reads a file from the local filesystem.', $this->sut->description());
    }

    public function testItAlwaysRequestsApproval(): void
    {
        $approval = $this->sut->shouldRequestApproval(new Request(['path' => '/tmp/x'], 'call_1'));

        self::assertNotNull($approval);
        self::assertSame('This tool is executed by the requesting client, not by the HAWKI runtime.', $approval->reason);
    }

    public function testItBuildsTheSchemaFromTheClientDefinition(): void
    {
        $schema = $this->sut->schema(new JsonSchemaTypeFactory());

        self::assertCount(2, $schema);
        self::assertArrayHasKey('path', $schema);
        self::assertArrayHasKey('offset', $schema);
        self::assertSame(['description' => 'Absolute file path.', 'type' => 'string'], $this->sortedSchema($schema['path']->toArray()));
    }

    public function testItDegradesUnsupportedPropertySchemasToDescribedStrings(): void
    {
        $this->logger->method('warning')->willReturnCallback(static function (string $message): void {
            static::assertStringContainsString('weird_param', $message);
        });

        $sut = new ClientTool(
            definition: new ToolDefinition(
                name: 'exotic',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'weird_param' => ['$ref' => '#/$defs/recursive', 'circular' => true],
                    ],
                ],
            ),
            logger: $this->logger,
        );

        $schema = $sut->schema(new JsonSchemaTypeFactory());

        self::assertArrayHasKey('weird_param', $schema);
        self::assertSame('string', $schema['weird_param']->toArray()['type']);
    }

    public function testItRefusesServerSideExecution(): void
    {
        $this->expectException(ClientToolExecutionException::class);
        $this->expectExceptionMessage('cannot be executed server-side');

        $this->sut->handle(new Request(['path' => '/tmp/x'], 'call_1'));
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function sortedSchema(array $schema): array
    {
        ksort($schema);

        return $schema;
    }
}
