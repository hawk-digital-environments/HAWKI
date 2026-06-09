<?php

namespace Tests\Feature\OpenApi;

use App\JsonApi\V1\AiProviders\AiProviderSchema;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Categories\CategorySchema;
use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use LaravelJsonApi\Core\Support\AppResolver;
use Tests\TestCase;

class ExampleBuilderFilterTest extends TestCase
{
    private ExampleBuilder $builder;

    /** @var array<string, object> */
    private array $schemas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $server = new Server(new AppResolver(fn () => app()), 'v1');
        $this->schemas = [
            'assistants' => new AssistantSchema($server),
            'assistant-categories' => new CategorySchema($server),
            'ai-providers' => new AiProviderSchema($server),
        ];

        $this->builder = new ExampleBuilder(new SchemaBuilder);
    }

    public function test_where_has_filter_returns_empty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'category');

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertSame([], $result);
    }

    public function test_custom_name_filter_returns_empty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'name');

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertSame([], $result);
    }

    public function test_custom_favorite_filter_returns_empty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'is_favorite');

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertSame([], $result);
    }

    public function test_where_filter_release_stage_returns_enum_example(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'release_stage');

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertNotEmpty($result);
        $this->assertContains('draft', $result);
    }

    public function test_where_in_filter_text_returns_resource_override(): void
    {
        $schema = $this->schemas['assistant-categories'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'text');
        $this->assertNotNull($filter);

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertSame(['programming'], $result);
    }

    public function test_custom_capability_filter_returns_empty(): void
    {
        $schema = $this->schemas['ai-providers'];
        $filter = collect($schema->filters())->first(fn ($f) => $f->key() === 'tool_capability');

        $result = $this->builder->getFilterExamples($schema, $filter);

        $this->assertSame([], $result);
    }
}
