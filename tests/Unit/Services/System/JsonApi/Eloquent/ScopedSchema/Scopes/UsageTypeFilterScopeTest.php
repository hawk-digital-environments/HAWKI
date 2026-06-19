<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes;

use App\Models\Scopes\Abstracts\AbstractUsageTypeScope;
use App\Models\Scopes\Generic\UsageTypeFilterScope;
use App\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes\Abstracts\AbstractUsageTypeFilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(UsageTypeFilterScope::class)]
#[CoversClass(AbstractUsageTypeFilterScope::class)]
#[CoversClass(AbstractUsageTypeScope::class)]
class UsageTypeFilterScopeTest extends TestCase
{
    private function makeScope(
        string    $fieldName = 'usage_type',
        ?\Closure $isAllowedToDisableResolver = null,
        ?\Closure $isDisabledResolver = null,
        ?\Closure $usageTypeResolver = null,
    ): UsageTypeFilterScope
    {
        $scope = new UsageTypeFilterScope(
            $fieldName,
            $isAllowedToDisableResolver,
            $isDisabledResolver,
            $usageTypeResolver,
        );
        $scope->setApp($this->app);
        $scope->setRequest(Request::create('/'));
        return $scope;
    }

    public function testItConstructs(): void
    {
        $sut = new UsageTypeFilterScope();
        static::assertInstanceOf(UsageTypeFilterScope::class, $sut);
    }

    // =========================================================================
    // Static query helpers
    // =========================================================================

    public function testItReturnsDisablingQueryValue(): void
    {
        static::assertSame('usage_type_filter', UsageTypeFilterScope::getDisablingQueryValue());
    }

    public function testItReturnsDisablingQuery(): void
    {
        static::assertSame('no_scope[]=usage_type_filter', UsageTypeFilterScope::getDisablingQuery());
    }

    // =========================================================================
    // apply() — filter applied
    // =========================================================================

    public function testItAppliesUsageTypeFilter(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('usage_type', 'test_usage');

        $sut = $this->makeScope(usageTypeResolver: fn() => 'test_usage');
        $sut->apply($builder, $model);
    }

    public function testItAppliesCustomFieldName(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('app_context', 'test_usage');

        $sut = $this->makeScope(fieldName: 'app_context', usageTypeResolver: fn() => 'test_usage');
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — disabled
    // =========================================================================

    public function testItSkipsFilterWhenDisabledAndAllowed(): void
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
