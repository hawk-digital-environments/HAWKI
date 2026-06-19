<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface;
use LaravelJsonApi\Eloquent\Schema;

class SchemaWithMultipleScopesFixture extends Schema implements ScopedSchemaInterface
{
    public static string $model = ValidModelFixture::class;

    public static function scopes(): iterable
    {
        yield new ValidScopeFixture();
        yield new ValidSecondScopeFixture();
    }

    public function fields(): iterable
    {
        return [];
    }
}
