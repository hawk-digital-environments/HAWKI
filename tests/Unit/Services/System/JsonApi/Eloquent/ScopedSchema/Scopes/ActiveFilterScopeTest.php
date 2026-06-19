<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes;

use App\Models\Scopes\Abstracts\AbstractScope;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes\Abstracts\AbstractActiveFilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(ActiveFilterScope::class)]
#[CoversClass(AbstractActiveFilterScope::class)]
#[CoversClass(AbstractScope::class)]
class ActiveFilterScopeTest extends TestCase
{
    private function makeScope(
        string    $fieldName = 'active',
        string    $activeValue = '1',
        ?\Closure $isAllowedToDisableResolver = null,
        ?\Closure $isDisabledResolver = null,
        string    $requestUri = '/',
    ): ActiveFilterScope
    {
        $scope = new ActiveFilterScope($fieldName, $activeValue, $isAllowedToDisableResolver, $isDisabledResolver);
        $scope->setApp($this->app);
        $scope->setRequest(Request::create($requestUri));
        return $scope;
    }

    public function testItConstructs(): void
    {
        $sut = new ActiveFilterScope();
        static::assertInstanceOf(ActiveFilterScope::class, $sut);
    }

    // =========================================================================
    // Static query helpers
    // =========================================================================

    public function testItReturnsDisablingQueryValue(): void
    {
        static::assertSame('active_filter', ActiveFilterScope::getDisablingQueryValue());
    }

    public function testItReturnsDisablingQueryParam(): void
    {
        static::assertSame('no_scope', ActiveFilterScope::getDisablingQueryParam());
    }

    public function testItReturnsDisablingQuery(): void
    {
        static::assertSame('no_scope[]=active_filter', ActiveFilterScope::getDisablingQuery());
    }

    // =========================================================================
    // apply() — active filter applied
    // =========================================================================

    public function testItAppliesActiveFilter(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('active', '1');

        $sut = $this->makeScope();
        $sut->apply($builder, $model);
    }

    public function testItAppliesCustomFieldAndValue(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('enabled', 'yes');

        $sut = $this->makeScope(fieldName: 'enabled', activeValue: 'yes');
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — disabled via request query param
    // =========================================================================

    public function testItSkipsFilterWhenDisabledViaQueryParam(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::never())->method('where');

        $sut = $this->makeScope(
            isAllowedToDisableResolver: fn() => true,
            requestUri: '/?no_scope[]=active_filter',
        );
        $sut->apply($builder, $model);
    }

    public function testItDoesNotSkipWhenQueryParamContainsDifferentToken(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('active', '1');

        $sut = $this->makeScope(requestUri: '/?no_scope[]=usage_type_filter');
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — disabled via resolver
    // =========================================================================

    public function testItSkipsFilterWhenDisabledResolverReturnsTrue(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::never())->method('where');

        $sut = $this->makeScope(
            isAllowedToDisableResolver: fn() => true,
            isDisabledResolver: fn() => true,
        );
        $sut->apply($builder, $model);
    }

    public function testItAbortsWith403WhenDisabledWithoutPermission(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Disabling scoping is not allowed for the current user or context.');

        $sut = $this->makeScope(
            isAllowedToDisableResolver: fn() => false,
            isDisabledResolver: fn() => true,
        );
        $sut->apply($builder, $model);
    }
}
