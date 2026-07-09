<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes;

use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\ContextualScopeWrapper;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ContextualScopeWrapper::class)]
class ContextualScopeWrapperTest extends TestCase
{
    private ScopeContext $scopeContext;
    private ServiceLocator $serviceLocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopeContext = new ScopeContext(fn() => true);
        $this->serviceLocator = new ServiceLocator(app());
    }

    private function makeSut(
        string $scopeKey = 'test-scope',
        Scope|string|\Closure|null $definition = null,
        \Closure|null $guard = null,
        string $modelClass = 'App\\Models\\Test'
    ): ContextualScopeWrapper
    {
        $registrar = new ScopeRegistrar($modelClass, fn() => true);
        $innerScope = $definition ?? $this->createMock(Scope::class);
        $registrar->addScope($scopeKey, $innerScope, $guard);

        $context = $this->scopeContext->getModelContext($modelClass);

        return new ContextualScopeWrapper($scopeKey, $registrar, $context, $this->serviceLocator);
    }

    private function getContext(string $modelClass = 'App\\Models\\Test'): ModelScopeContext
    {
        return $this->scopeContext->getModelContext($modelClass);
    }

    // =========================================================================
    // getScopeKey
    // =========================================================================

    public function testItGetScopeKeyReturnsKey(): void
    {
        $sut = $this->makeSut('active-filter');

        static::assertSame('active-filter', $sut->getScopeKey());
    }

    // =========================================================================
    // getFullScopeKey
    // =========================================================================

    public function testItGetFullScopeKeyDelegatesToContext(): void
    {
        $sut = $this->makeSut('active-filter');
        $context = $this->getContext();

        static::assertSame($context->getFullScopeKey('active-filter'), $sut->getFullScopeKey());
    }

    // =========================================================================
    // getContext
    // =========================================================================

    public function testItGetContextReturnsModelScopeContext(): void
    {
        $sut = $this->makeSut();

        static::assertInstanceOf(ModelScopeContext::class, $sut->getContext());
    }

    public function testItGetContextReturnsSameInstanceAsFromScopeContext(): void
    {
        $sut = $this->makeSut(modelClass: 'App\\Models\\Test');
        $expected = $this->scopeContext->getModelContext('App\\Models\\Test');

        static::assertSame($expected, $sut->getContext());
    }

    // =========================================================================
    // disable
    // =========================================================================

    public function testItDisableDisablesScopeOnContext(): void
    {
        $sut = $this->makeSut('my-scope');

        $sut->disable();

        static::assertTrue($this->getContext()->isScopeDisabled('my-scope'));
    }

    public function testItDisablePassesCallbackToContext(): void
    {
        $callback = fn() => false;
        $sut = $this->makeSut('my-scope');
        $sut->disable($callback);

        static::assertSame($callback, $this->getContext()->getOnDisableNotAllowed('my-scope'));
    }

    // =========================================================================
    // evaluateDisabling
    // =========================================================================

    public function testItEvaluateDisablingReturnsFalseWhenScopeIsNotDisabled(): void
    {
        $sut = $this->makeSut('my-scope');

        static::assertFalse($sut->evaluateDisabling());
    }

    public function testItEvaluateDisablingReturnsTrueWhenScopeIsDisabledAndGuardAllows(): void
    {
        $sut = $this->makeSut('my-scope', guard: fn() => true);
        $this->getContext()->disableScope('my-scope');

        static::assertTrue($sut->evaluateDisabling());
    }

    public function testItEvaluateDisablingReturnsFalseWhenGuardDeniesAndNotAllowedCallbackReturnsFalse(): void
    {
        $sut = $this->makeSut('my-scope', guard: fn() => false);
        $this->getContext()->disableScope('my-scope', fn(string $key, ModelScopeContext $ctx) => false);

        static::assertFalse($sut->evaluateDisabling());
    }

    public function testItEvaluateDisablingReturnsTrueWhenGuardDeniesButNotAllowedCallbackForcesDisable(): void
    {
        $sut = $this->makeSut('my-scope', guard: fn() => false);
        $this->getContext()->disableScope('my-scope', fn(string $key, ModelScopeContext $ctx) => true);

        static::assertTrue($sut->evaluateDisabling());
    }

    // =========================================================================
    // apply
    // =========================================================================

    public function testItApplySkipsInnerScopeWhenDisabled(): void
    {
        $innerScope = $this->createMock(Scope::class);
        $innerScope->expects(static::never())->method('apply');

        $sut = $this->makeSut('my-scope', $innerScope, guard: fn() => true);
        $this->getContext()->disableScope('my-scope');

        $sut->apply($this->createMock(Builder::class), $this->createMock(Model::class));
    }

    public function testItApplyDelegatesToInnerScopeWhenNotDisabled(): void
    {
        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);

        $innerScope = $this->createMock(Scope::class);
        $innerScope->expects(static::once())->method('apply')->with($builder, $model);

        $sut = $this->makeSut('my-scope', $innerScope);

        $sut->apply($builder, $model);
    }
}
