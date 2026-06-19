<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema;

use App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTrait;
use App\Services\System\JsonApi\Exceptions\FailedToApplyModelScopeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Schema;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\NonSchemaFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\NotAModelFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\NotAScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithAbstractScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithInvalidModelFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithInvalidScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithMissingModelFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithMissingScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithMultipleScopesFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithRepeatableScopesFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\SchemaWithoutAnnotationFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\TraitProxy;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\ValidAbstractScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\ValidModelFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\ValidSchemaFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\ValidScopeFixture;
use Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures\ValidSecondScopeFixture;

#[CoversTrait(ScopedSchemaServerTrait::class)]
class ScopedSchemaServerTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();
        parent::tearDown();
    }

    private function makeSut(): TraitProxy
    {
        $sut = new TraitProxy();
        $sut->setFailOnMissingLocalService(true);
        $sut->setService(Application::class, $this->app);
        $sut->setService(Request::class, Request::create('/'));
        return $sut;
    }

    // =========================================================================
    // Silent skipping
    // =========================================================================

    public function testItSkipsNonExistentSchemaClass(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels(['App\NonExistent\SchemaClass']);

        static::assertEmpty(ValidModelFixture::getGlobalScopes());
    }

    public function testItSkipsSchemaWithoutInterface(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([SchemaWithoutAnnotationFixture::class]);

        static::assertEmpty(ValidModelFixture::getGlobalScopes());
    }

    public function testItSkipsEmptySchemaList(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([]);

        static::assertEmpty(ValidModelFixture::getGlobalScopes());
    }

    // =========================================================================
    // Error: invalid schema class
    // =========================================================================

    public function testItThrowsWhenSchemaDoesNotExtendEloquentSchema(): void
    {
        $sut = $this->makeSut();

        $this->expectException(FailedToApplyModelScopeException::class);
        $this->expectExceptionMessage(sprintf(
            'Schema class "%s" does not extend %s. Model scopes only work with Eloquent schemas.',
            NonSchemaFixture::class,
            Schema::class
        ));

        $sut->applyScopesToModels([NonSchemaFixture::class]);
    }

    // =========================================================================
    // Error: missing model class
    // =========================================================================

    public function testItThrowsWhenModelClassDoesNotExist(): void
    {
        $sut = $this->makeSut();

        $this->expectException(FailedToApplyModelScopeException::class);
        $this->expectExceptionMessage(sprintf(
            'Model class "%s" does not exist. Could not apply model scope for schema "%s".',
            'App\Services\System\JsonApi\ScopedSchema\NonExistentModelClass',
            SchemaWithMissingModelFixture::class
        ));

        $sut->applyScopesToModels([SchemaWithMissingModelFixture::class]);
    }

    // =========================================================================
    // Error: invalid model class
    // =========================================================================

    public function testItThrowsWhenModelClassDoesNotExtendEloquentModel(): void
    {
        $sut = $this->makeSut();

        $this->expectException(FailedToApplyModelScopeException::class);
        $this->expectExceptionMessage(sprintf(
            'Model class "%s" does not implement %s. Could not apply model scope for schema "%s".',
            NotAModelFixture::class,
            Model::class,
            SchemaWithInvalidModelFixture::class
        ));

        $sut->applyScopesToModels([SchemaWithInvalidModelFixture::class]);
    }

    // =========================================================================
    // Error: missing scope class
    // =========================================================================

    public function testItThrowsWhenScopeClassDoesNotExist(): void
    {
        $sut = $this->makeSut();

        $this->expectException(FailedToApplyModelScopeException::class);
        $this->expectExceptionMessage(sprintf(
            'Scope class "%s" does not exist. Could not apply model scope for schema "%s".',
            'App\Services\System\JsonApi\ScopedSchema\NonExistentScopeClass',
            SchemaWithMissingScopeFixture::class
        ));

        $sut->applyScopesToModels([SchemaWithMissingScopeFixture::class]);
    }

    // =========================================================================
    // Error: invalid scope class
    // =========================================================================

    public function testItThrowsWhenScopeClassDoesNotImplementScopeInterface(): void
    {
        $sut = $this->makeSut();

        $this->expectException(FailedToApplyModelScopeException::class);
        $this->expectExceptionMessage(sprintf(
            'Scope class "%s" does not implement %s. Could not apply model scope for schema "%s".',
            NotAScopeFixture::class,
            \Illuminate\Database\Eloquent\Scope::class,
            SchemaWithInvalidScopeFixture::class
        ));

        $sut->applyScopesToModels([SchemaWithInvalidScopeFixture::class]);
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function testItRegistersGlobalScopeOnModel(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([ValidSchemaFixture::class]);

        $scopes = array_values(ValidModelFixture::getGlobalScopes());
        static::assertCount(1, $scopes);
        static::assertInstanceOf(ValidScopeFixture::class, $scopes[0]);
    }

    public function testItSkipsNonInterfaceSchemasInMixedList(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([SchemaWithoutAnnotationFixture::class, ValidSchemaFixture::class]);

        $scopes = array_values(ValidModelFixture::getGlobalScopes());
        static::assertCount(1, $scopes);
        static::assertInstanceOf(ValidScopeFixture::class, $scopes[0]);
    }

    public function testItRegistersMultipleScopesFromGenerator(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([SchemaWithMultipleScopesFixture::class]);

        $scopes = array_values(ValidModelFixture::getGlobalScopes());
        static::assertCount(2, $scopes);
        static::assertInstanceOf(ValidScopeFixture::class, $scopes[0]);
        static::assertInstanceOf(ValidSecondScopeFixture::class, $scopes[1]);
    }

    public function testItRegistersGlobalScopeUsingClassString(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([SchemaWithRepeatableScopesFixture::class]);

        $scopes = array_values(ValidModelFixture::getGlobalScopes());
        static::assertCount(1, $scopes);
        static::assertInstanceOf(ValidScopeFixture::class, $scopes[0]);
    }

    public function testItInjectsAppAndRequestIntoAbstractScope(): void
    {
        $sut = $this->makeSut();

        $sut->applyScopesToModels([SchemaWithAbstractScopeFixture::class]);

        $scopes = array_values(ValidModelFixture::getGlobalScopes());
        static::assertCount(1, $scopes);
        static::assertInstanceOf(ValidAbstractScopeFixture::class, $scopes[0]);
        static::assertTrue($scopes[0]->hasAppAndRequestInjected());
    }
}
