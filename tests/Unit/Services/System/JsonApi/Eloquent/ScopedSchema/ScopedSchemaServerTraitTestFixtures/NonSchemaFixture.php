<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface;
use Illuminate\Database\Eloquent\Scope;

/**
 * Implements ScopedSchemaInterface but does NOT extend Schema — triggers the "invalid schema" error.
 */
class NonSchemaFixture implements ScopedSchemaInterface
{
    public static function scopes(): iterable
    {
        yield new ValidScopeFixture();
    }
}
