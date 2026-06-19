<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\Scopes;

use App\Models\Scopes\Generic\ActiveFilterOnRelationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(ActiveFilterOnRelationScope::class)]
class ActiveFilterOnRelationScopeTest extends TestCase
{
    private function makeScope(
        string    $relationName = 'provider',
        string    $fieldName = 'active',
        string    $activeValue = '1',
        ?\Closure $isAllowedToDisableResolver = null,
        ?\Closure $isDisabledResolver = null,
    ): ActiveFilterOnRelationScope
    {
        $scope = new ActiveFilterOnRelationScope(
            $relationName,
            $fieldName,
            $activeValue,
            $isAllowedToDisableResolver,
            $isDisabledResolver,
        );
        $scope->setApp($this->app);
        $scope->setRequest(Request::create('/'));
        return $scope;
    }

    public function testItConstructs(): void
    {
        $sut = new ActiveFilterOnRelationScope('provider');
        static::assertInstanceOf(ActiveFilterOnRelationScope::class, $sut);
    }

    // =========================================================================
    // apply() — filter applied
    // =========================================================================

    public function testItAppliesActiveFilterOnRelation(): void
    {
        $innerQuery = $this->createMock(Builder::class);
        $innerQuery->expects(static::once())->method('where')->with('active', '1');

        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())
            ->method('whereHas')
            ->with('provider', static::isInstanceOf(\Closure::class))
            ->willReturnCallback(function (string $relation, \Closure $closure) use ($innerQuery, $builder) {
                $closure($innerQuery);
                return $builder;
            });

        $sut = $this->makeScope();
        $sut->apply($builder, $model);
    }

    public function testItAppliesCustomRelationFieldAndValue(): void
    {
        $innerQuery = $this->createMock(Builder::class);
        $innerQuery->expects(static::once())->method('where')->with('enabled', 'yes');

        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);
        $builder->expects(static::once())
            ->method('whereHas')
            ->with('partner', static::isInstanceOf(\Closure::class))
            ->willReturnCallback(function (string $relation, \Closure $closure) use ($innerQuery, $builder) {
                $closure($innerQuery);
                return $builder;
            });

        $sut = $this->makeScope(relationName: 'partner', fieldName: 'enabled', activeValue: 'yes');
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
