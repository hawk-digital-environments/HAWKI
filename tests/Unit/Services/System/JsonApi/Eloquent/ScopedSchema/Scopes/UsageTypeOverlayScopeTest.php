<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes;

use App\Models\Scopes\Generic\UsageTypeOverlayScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(UsageTypeOverlayScope::class)]
class UsageTypeOverlayScopeTest extends TestCase
{
    private function makeScope(
        string|array $discriminatorFields = 'model_type',
        string       $fieldName = 'usage_type',
        string       $defaultUsageType = 'main_app',
        ?\Closure    $isAllowedToDisableResolver = null,
        ?\Closure    $isDisabledResolver = null,
        ?\Closure    $usageTypeResolver = null,
    ): UsageTypeOverlayScope
    {
        $scope = new UsageTypeOverlayScope(
            $discriminatorFields,
            $fieldName,
            $defaultUsageType,
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
        $sut = new UsageTypeOverlayScope('model_type');
        static::assertInstanceOf(UsageTypeOverlayScope::class, $sut);
    }

    // =========================================================================
    // Static query helpers
    // =========================================================================

    public function testItReturnsDisablingQueryValue(): void
    {
        static::assertSame('usage_type_overlay', UsageTypeOverlayScope::getDisablingQueryValue());
    }

    public function testItReturnsDisablingQuery(): void
    {
        static::assertSame('no_scope[]=usage_type_overlay', UsageTypeOverlayScope::getDisablingQuery());
    }

    // =========================================================================
    // apply() — default usage type: direct filter
    // =========================================================================

    public function testItAppliesDirectFilterWhenCurrentUsageTypeIsDefault(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('usage_type', 'main_app');

        $sut = $this->makeScope(usageTypeResolver: fn() => 'main_app');
        $sut->apply($builder, $model);
    }

    public function testItAppliesDirectFilterWithCustomFieldAndDefaultType(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())->method('where')->with('app_context', 'default');

        $sut = $this->makeScope(
            fieldName: 'app_context',
            defaultUsageType: 'default',
            usageTypeResolver: fn() => 'default',
        );
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — non-default usage type: overlay query
    // =========================================================================

    public function testItAppliesOverlayGroupingForNonDefaultUsageType(): void
    {
        $innerModel = $this->createMock(Model::class);
        $innerModel->method('getTable')->willReturn('test_table');

        $builder = $this->createMock(Builder::class);
        $builder->method('getModel')->willReturn($innerModel);
        $builder->expects(static::once())
            ->method('where')
            ->with(static::isInstanceOf(\Closure::class));

        $model = $this->createMock(Model::class);

        $sut = $this->makeScope(usageTypeResolver: fn() => 'ext_app');
        $sut->apply($builder, $model);
    }

    public function testItAcceptsArrayOfDiscriminatorFields(): void
    {
        $innerModel = $this->createMock(Model::class);
        $innerModel->method('getTable')->willReturn('test_table');

        $builder = $this->createMock(Builder::class);
        $builder->method('getModel')->willReturn($innerModel);
        $builder->expects(static::once())
            ->method('where')
            ->with(static::isInstanceOf(\Closure::class));

        $model = $this->createMock(Model::class);

        $sut = $this->makeScope(
            discriminatorFields: ['model_type', 'provider_id'],
            usageTypeResolver: fn() => 'ext_app',
        );
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — disabled
    // =========================================================================

    public function testItSkipsWhenDisabledAndAllowed(): void
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
