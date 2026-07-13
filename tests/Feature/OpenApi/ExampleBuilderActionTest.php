<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;

use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use Tests\TestCase;

#[CoversClass(ExampleBuilder::class)]
class ExampleBuilderActionTest extends TestCase
{
    private ExampleBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ExampleBuilder(new SchemaBuilder());
    }

    public function testRemixActionResponseExampleIsDefined(): void
    {
        $example = $this->builder->getActionResponseExample('assistants', 'remix');

        self::assertNotNull($example);
        self::assertSame('assistants', $example['data']['type']);
        self::assertSame('2', $example['data']['id']);
    }

    public function testUndefinedActionReturnsNullExample(): void
    {
        self::assertNull($this->builder->getActionRequestExample('assistants', 'nonexistent'));
        self::assertNull($this->builder->getActionResponseExample('assistants', 'nonexistent'));
    }
}
