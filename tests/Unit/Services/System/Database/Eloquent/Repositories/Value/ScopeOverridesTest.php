<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Value;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ScopeOverrides::class)]
class ScopeOverridesTest extends TestCase
{
    // =========================================================================
    // withContextConfigurator
    // =========================================================================

    public function testItWithContextConfiguratorCallsCallbackWithContext(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $called = false;

        $sut = new ScopeOverrides();
        $sut->withContextConfigurator(function (ModelScopeContext $ctx) use ($context, &$called): void {
            static::assertSame($context, $ctx);
            $called = true;
        });

        $sut->apply($context);

        static::assertTrue($called);
    }

    public function testItWithContextConfiguratorReturnsSelf(): void
    {
        $sut = new ScopeOverrides();

        static::assertSame($sut, $sut->withContextConfigurator(static fn() => null));
    }

    // =========================================================================
    // withDisabled (single key)
    // =========================================================================

    public function testItWithDisabledCallsDisableScopeOnContext(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('my-scope', null);

        $sut = new ScopeOverrides();
        $sut->withDisabled('my-scope');
        $sut->apply($context);
    }

    public function testItWithDisabledPassesCallbackToContext(): void
    {
        $callback = static fn() => false;

        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('my-scope', $callback);

        $sut = new ScopeOverrides();
        $sut->withDisabled('my-scope', $callback);
        $sut->apply($context);
    }

    public function testItWithDisabledAcceptsArrayOfKeys(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::exactly(2))
            ->method('disableScope');

        $sut = new ScopeOverrides();
        $sut->withDisabled(['scope-a', 'scope-b']);
        $sut->apply($context);
    }

    public function testItWithDisabledReturnsSelf(): void
    {
        $sut = new ScopeOverrides();

        static::assertSame($sut, $sut->withDisabled('my-scope'));
    }

    // =========================================================================
    // withForcefullyDisabled
    // =========================================================================

    public function testItWithForcefullyDisabledCallsDisableScopeWithForceCallback(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('my-scope', static::isInstanceOf(\Closure::class));

        $sut = new ScopeOverrides();
        $sut->withForcefullyDisabled('my-scope');
        $sut->apply($context);
    }

    public function testItWithForcefullyDisabledForceCallbackReturnsTrue(): void
    {
        // The force-disable callback must return true to signal "bypass not-allowed guard".
        $capturedCallback = null;
        $context = $this->createMock(ModelScopeContext::class);
        $context->method('disableScope')
            ->willReturnCallback(function (string $key, ?\Closure $cb) use ($context, &$capturedCallback): ModelScopeContext {
                $capturedCallback = $cb;
                return $context;
            });

        $sut = new ScopeOverrides();
        $sut->withForcefullyDisabled('my-scope');
        $sut->apply($context);

        static::assertNotNull($capturedCallback);
        static::assertTrue($capturedCallback());
    }

    public function testItWithForcefullyDisabledReturnsSelf(): void
    {
        $sut = new ScopeOverrides();

        static::assertSame($sut, $sut->withForcefullyDisabled('my-scope'));
    }

    // =========================================================================
    // withAllDisabled
    // =========================================================================

    public function testItWithAllDisabledCallsSetAllScopesLocallyDisabled(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, null);

        $sut = new ScopeOverrides();
        $sut->withAllDisabled();
        $sut->apply($context);
    }

    public function testItWithAllDisabledPassesCallbackToContext(): void
    {
        $callback = static fn() => false;

        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, $callback);

        $sut = new ScopeOverrides();
        $sut->withAllDisabled($callback);
        $sut->apply($context);
    }

    public function testItWithAllDisabledReturnsSelf(): void
    {
        $sut = new ScopeOverrides();

        static::assertSame($sut, $sut->withAllDisabled());
    }

    // =========================================================================
    // withAllForcefullyDisabled
    // =========================================================================

    public function testItWithAllForcefullyDisabledPassesForceCallbackToContext(): void
    {
        $capturedCallback = null;
        $context = $this->createMock(ModelScopeContext::class);
        $context->method('setAllScopesLocallyDisabled')
            ->willReturnCallback(function (bool $disabled, ?\Closure $cb) use ($context, &$capturedCallback): ModelScopeContext {
                $capturedCallback = $cb;
                return $context;
            });

        $sut = new ScopeOverrides();
        $sut->withAllForcefullyDisabled();
        $sut->apply($context);

        static::assertNotNull($capturedCallback);
        static::assertTrue($capturedCallback());
    }

    public function testItWithAllForcefullyDisabledReturnsSelf(): void
    {
        $sut = new ScopeOverrides();

        static::assertSame($sut, $sut->withAllForcefullyDisabled());
    }

    // =========================================================================
    // apply — no-op when nothing configured
    // =========================================================================

    public function testItApplyDoesNothingWhenUnconfigured(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::never())->method('setAllScopesLocallyDisabled');
        $context->expects(static::never())->method('disableScope');

        $sut = new ScopeOverrides();
        $sut->apply($context);
    }

    // =========================================================================
    // Static factory methods
    // =========================================================================

    public function testItMakeWithDisabledCreatesInstance(): void
    {
        $sut = ScopeOverrides::makeWithDisabled('my-scope');

        static::assertInstanceOf(ScopeOverrides::class, $sut);
    }

    public function testItMakeWithDisabledConfiguresCorrectScope(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('my-scope', null);

        ScopeOverrides::makeWithDisabled('my-scope')->apply($context);
    }

    public function testItMakeWithForcefullyDisabledCreatesInstance(): void
    {
        $sut = ScopeOverrides::makeWithForcefullyDisabled('my-scope');

        static::assertInstanceOf(ScopeOverrides::class, $sut);
    }

    public function testItMakeWithAllDisabledCreatesInstance(): void
    {
        $sut = ScopeOverrides::makeWithAllDisabled();

        static::assertInstanceOf(ScopeOverrides::class, $sut);
    }

    public function testItMakeWithAllDisabledDisablesAll(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, null);

        ScopeOverrides::makeWithAllDisabled()->apply($context);
    }

    public function testItMakeWithAllForcefullyDisabledCreatesInstance(): void
    {
        $sut = ScopeOverrides::makeWithAllForcefullyDisabled();

        static::assertInstanceOf(ScopeOverrides::class, $sut);
    }

    public function testItMakeCreatesInstanceWithAllDisabledByDefault(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('setAllScopesLocallyDisabled')
            ->with(true, null);

        ScopeOverrides::make()->apply($context);
    }

    public function testItMakeCreatesInstanceWithSpecificScopeKeys(): void
    {
        $context = $this->createMock(ModelScopeContext::class);
        $context->expects(static::once())
            ->method('disableScope')
            ->with('scope-x', null);

        ScopeOverrides::make('scope-x')->apply($context);
    }

    public function testItMakePassesTrueAsForceDisableCallback(): void
    {
        $capturedCallback = null;
        $context = $this->createMock(ModelScopeContext::class);
        $context->method('setAllScopesLocallyDisabled')
            ->willReturnCallback(function (bool $disabled, ?\Closure $cb) use ($context, &$capturedCallback): ModelScopeContext {
                $capturedCallback = $cb;
                return $context;
            });

        ScopeOverrides::make(true, true)->apply($context);

        static::assertNotNull($capturedCallback);
        static::assertTrue($capturedCallback());
    }
}
