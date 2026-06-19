<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes;

use App\Models\Scopes\Generic\UsageTypeFilterOnRelationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(UsageTypeFilterOnRelationScope::class)]
class UsageTypeFilterOnRelationScopeTest extends TestCase
{
    private function makeScope(
        string    $relationName = 'usageRules',
        string    $fieldName = 'usage_type',
        ?\Closure $isAllowedToDisableResolver = null,
        ?\Closure $isDisabledResolver = null,
        ?\Closure $usageTypeResolver = null,
    ): UsageTypeFilterOnRelationScope
    {
        $scope = new UsageTypeFilterOnRelationScope(
            $relationName,
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
        $sut = new UsageTypeFilterOnRelationScope('usageRules');
        static::assertInstanceOf(UsageTypeFilterOnRelationScope::class, $sut);
    }

    // =========================================================================
    // apply() — filter applied
    // =========================================================================

    public function testItAppliesUsageTypeFilterOnRelation(): void
    {
        $innerQuery = $this->createMock(Builder::class);
        $innerQuery->expects(static::once())->method('where')->with('usage_type', 'test_usage');

        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())
            ->method('whereHas')
            ->with('usageRules', static::isInstanceOf(\Closure::class))
            ->willReturnCallback(function (string $relation, \Closure $closure) use ($innerQuery, $builder) {
                $closure($innerQuery);
                return $builder;
            });

        $sut = $this->makeScope(usageTypeResolver: fn() => 'test_usage');
        $sut->apply($builder, $model);
    }

    public function testItAppliesCustomRelationAndField(): void
    {
        $innerQuery = $this->createMock(Builder::class);
        $innerQuery->expects(static::once())->method('where')->with('app_context', 'ext_app');

        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())
            ->method('whereHas')
            ->with('permissions', static::isInstanceOf(\Closure::class))
            ->willReturnCallback(function (string $relation, \Closure $closure) use ($innerQuery, $builder) {
                $closure($innerQuery);
                return $builder;
            });

        $sut = $this->makeScope(
            relationName: 'permissions',
            fieldName: 'app_context',
            usageTypeResolver: fn() => 'ext_app',
        );
        $sut->apply($builder, $model);
    }

    // =========================================================================
    // apply() — disabled
    // =========================================================================

    public function testItSkipsFilterWhenDisabledAndAllowed(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::never())->method('whereHas');

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
