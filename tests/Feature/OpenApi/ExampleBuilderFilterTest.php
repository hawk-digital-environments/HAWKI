<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use LaravelJsonApi\Core\Support\AppResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ExampleBuilder::class)]
class ExampleBuilderFilterTest extends TestCase
{
    private ExampleBuilder $builder;

    /**
     * @var array<string, object>
     */
    private array $schemas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $server = new Server(new AppResolver(static fn () => app()), 'v1');
        $schemas = $server->schemas();
        $this->schemas = [
            'assistants' => $schemas->schemaFor('assistants'),
            'assistant-categories' => $schemas->schemaFor('assistant-categories'),
            'ai-providers' => $schemas->schemaFor('ai-providers'),
        ];

        $this->builder = new ExampleBuilder(new SchemaBuilder());
    }

    public function testWhereHasFilterReturnsEmpty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'assistant_category');

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertSame([], $result);
    }

    public function testCustomNameFilterReturnsEmpty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'name');

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertSame([], $result);
    }

    public function testCustomFavoriteFilterReturnsEmpty(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'is_favorite');

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertSame([], $result);
    }

    public function testWhereFilterReleaseStageReturnsEnumExample(): void
    {
        $schema = $this->schemas['assistants'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'release_stage');

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertNotEmpty($result);
        self::assertContains('draft', $result);
    }

    public function testWhereInFilterTextReturnsResourceOverride(): void
    {
        $schema = $this->schemas['assistant-categories'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'text');
        self::assertNotNull($filter);

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertSame(['programming'], $result);
    }

    public function testCustomCapabilityFilterReturnsEmpty(): void
    {
        $schema = $this->schemas['ai-providers'];
        $filter = collect($schema->filters())->first(static fn ($f) => $f->key() === 'tool_capability');

        $result = $this->builder->getFilterExamples($schema, $filter);

        self::assertSame([], $result);
    }
}
