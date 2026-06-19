<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Yields a scope as a class-string to verify container-based resolution.
 */
class SchemaWithRepeatableScopesFixture extends Schema implements ScopedSchemaInterface
{
    public static string $model = ValidModelFixture::class;

    public static function scopes(): iterable
    {
        yield ValidScopeFixture::class;
    }

    public function fields(): iterable
    {
        return [];
    }
}
