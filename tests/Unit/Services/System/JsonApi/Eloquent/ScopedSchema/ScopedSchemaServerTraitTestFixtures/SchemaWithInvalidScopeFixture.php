<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Yields a class that does not implement Scope — triggers the "invalid scope class" error.
 */
class SchemaWithInvalidScopeFixture extends Schema implements ScopedSchemaInterface
{
    public static string $model = ValidModelFixture::class;

    public static function scopes(): iterable
    {
        yield new NotAScopeFixture();
    }

    public function fields(): iterable
    {
        return [];
    }
}
