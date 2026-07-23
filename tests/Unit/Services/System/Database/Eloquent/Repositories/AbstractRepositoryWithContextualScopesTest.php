<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories;

use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopesTestFixtures\TestRepositoryForScopes;

#[CoversClass(AbstractRepositoryWithContextualScopes::class)]
class AbstractRepositoryWithContextualScopesTest extends TestCase
{
    private TestRepositoryForScopes $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new TestRepositoryForScopes();
    }

    // =========================================================================
    // makeScopeOverrides — null (no-op)
    // =========================================================================

    public function testItMakeScopeOverridesWithNullReturnsEmptyOverrides(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::never())->method('setAllScopesLocallyDisabled');
        $context->expects(static::never())->method('disableScope');

        $this->sut->makeScopeOverrides(null)->apply($context);
    }

    // =========================================================================
    // makeScopeOverrides — disable all
    // =========================================================================

    public function testItMakeScopeOverridesWithTrueDisablesAllScopes(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, null);

        $this->sut->makeScopeOverrides(true)->apply($context);
    }

    public function testItMakeScopeOverridesPassesCallbackForAllDisabled(): void
    {
        $callback = static fn() => false;

        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, $callback);

        $this->sut->makeScopeOverrides(true, $callback)->apply($context);
    }

    public function testItMakeScopeOverridesWithOnNotAllowedTrueUsesForceDisableCallback(): void
    {
        $capturedCallback = null;

        $context = $this->createMock(ModelScopeContext::class);
        $context->method('setAllScopesLocallyDisabled')
            ->willReturnCallback(function (bool $disabled, ?\Closure $cb) use ($context, &$capturedCallback): ModelScopeContext {
                $capturedCallback = $cb;
                return $context;
            });

        $this->sut->makeScopeOverrides(true, true)->apply($context);

        static::assertNotNull($capturedCallback);
        static::assertTrue($capturedCallback());
    }

    // =========================================================================
    // makeScopeOverrides — disable specific scope(s)
    // =========================================================================

    public function testItMakeScopeOverridesWithStringScopeDisablesThatScope(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('active_filter', null);

        $this->sut->makeScopeOverrides('active_filter')->apply($context);
    }

    public function testItMakeScopeOverridesWithArrayDisablesEachScope(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::exactly(2))->method('disableScope');

        $this->sut->makeScopeOverrides(['scope-a', 'scope-b'])->apply($context);
    }

    public function testItMakeScopeOverridesReturnsScopeOverridesInstance(): void
    {
        $result = $this->sut->makeScopeOverrides(null);

        static::assertInstanceOf(ScopeOverrides::class, $result);
    }

    // =========================================================================
    // getQuery — asserts model has HasContextualScopesTrait
    // =========================================================================

    public function testItThrowsWhenModelDoesNotHaveContextualScopesTrait(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(HasContextualScopesTrait::class);

        $this->sut->findAll();
    }
}
