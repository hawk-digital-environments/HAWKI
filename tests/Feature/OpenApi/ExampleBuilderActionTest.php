<?php

namespace Tests\Feature\OpenApi;

use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use Tests\TestCase;

class ExampleBuilderActionTest extends TestCase
{
    private ExampleBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ExampleBuilder(new SchemaBuilder);
    }

    public function test_remix_action_response_example_is_defined(): void
    {
        $example = $this->builder->getActionResponseExample('assistants', 'remix');

        $this->assertNotNull($example);
        $this->assertSame('assistants', $example['data']['type']);
        $this->assertSame('2', $example['data']['id']);
    }

    public function test_undefined_action_returns_null_example(): void
    {
        $this->assertNull($this->builder->getActionRequestExample('assistants', 'nonexistent'));
        $this->assertNull($this->builder->getActionResponseExample('assistants', 'nonexistent'));
    }
}
