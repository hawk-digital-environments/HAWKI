<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use LaravelJsonApi\Eloquent\Schema;

class SchemaWithoutAnnotationFixture extends Schema
{
    public static string $model = ValidModelFixture::class;

    public function fields(): iterable
    {
        return [];
    }
}
