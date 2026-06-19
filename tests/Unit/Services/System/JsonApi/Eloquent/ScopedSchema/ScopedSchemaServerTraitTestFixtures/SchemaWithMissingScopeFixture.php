<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Yields a class-string that does not exist — triggers the "missing scope class" error.
 */
class SchemaWithMissingScopeFixture extends Schema implements ScopedSchemaInterface
{
    public static string $model = ValidModelFixture::class;

    public static function scopes(): iterable
    {
        yield 'App\Services\System\JsonApi\ScopedSchema\NonExistentScopeClass';
    }

    public function fields(): iterable
    {
        return [];
    }
}
