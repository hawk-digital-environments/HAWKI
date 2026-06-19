<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Exceptions;

use App\Services\System\JsonApi\Exceptions\FailedToApplyModelScopeException;
use App\Services\System\JsonApi\Exceptions\JsonApiExceptionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LaravelJsonApi\Eloquent\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FailedToApplyModelScopeException::class)]
class FailedToApplyModelScopeExceptionTest extends TestCase
{
    public function testItImplementsJsonApiExceptionInterface(): void
    {
        $sut = FailedToApplyModelScopeException::forInvalidSchemaClass('App\Schema');

        static::assertInstanceOf(JsonApiExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forInvalidSchemaClass
    // =========================================================================

    public function testItCreatesForInvalidSchemaClass(): void
    {
        $sut = FailedToApplyModelScopeException::forInvalidSchemaClass('App\SomeSchema');

        static::assertInstanceOf(FailedToApplyModelScopeException::class, $sut);
        static::assertSame(
            sprintf('Schema class "App\SomeSchema" does not extend %s. Model scopes only work with Eloquent schemas.', Schema::class),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forMissingScopeClass
    // =========================================================================

    public function testItCreatesForMissingScopeClass(): void
    {
        $sut = FailedToApplyModelScopeException::forMissingScopeClass('App\SomeScope', 'App\SomeSchema');

        static::assertInstanceOf(FailedToApplyModelScopeException::class, $sut);
        static::assertSame(
            'Scope class "App\SomeScope" does not exist. Could not apply model scope for schema "App\SomeSchema".',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidScopeClass
    // =========================================================================

    public function testItCreatesForInvalidScopeClass(): void
    {
        $sut = FailedToApplyModelScopeException::forInvalidScopeClass('App\SomeScope', 'App\SomeSchema');

        static::assertInstanceOf(FailedToApplyModelScopeException::class, $sut);
        static::assertSame(
            sprintf('Scope class "App\SomeScope" does not implement %s. Could not apply model scope for schema "App\SomeSchema".', Scope::class),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forMissingModelClass
    // =========================================================================

    public function testItCreatesForMissingModelClass(): void
    {
        $sut = FailedToApplyModelScopeException::forMissingModelClass('App\SomeModel', 'App\SomeSchema');

        static::assertInstanceOf(FailedToApplyModelScopeException::class, $sut);
        static::assertSame(
            'Model class "App\SomeModel" does not exist. Could not apply model scope for schema "App\SomeSchema".',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidModelClass
    // =========================================================================

    public function testItCreatesForInvalidModelClass(): void
    {
        $sut = FailedToApplyModelScopeException::forInvalidModelClass('App\SomeModel', 'App\SomeSchema');

        static::assertInstanceOf(FailedToApplyModelScopeException::class, $sut);
        static::assertSame(
            sprintf('Model class "App\SomeModel" does not implement %s. Could not apply model scope for schema "App\SomeSchema".', Model::class),
            $sut->getMessage()
        );
    }
}
