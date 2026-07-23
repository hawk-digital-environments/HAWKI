<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelScopeContext::class)]
class ModelScopeContextTest extends TestCase
{
    private ScopeContext $scopeContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopeContext = new ScopeContext(fn() => true);
    }

    private function makeSut(string $modelClass = 'App\\Models\\Test'): ModelScopeContext
    {
        return $this->scopeContext->getModelContext($modelClass);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();

        static::assertInstanceOf(ModelScopeContext::class, $sut);
        static::assertSame('App\\Models\\Test', $sut->modelClass);
    }

    // =========================================================================
    // isScopeDisabled
    // =========================================================================

    public function testItIsScopeDisabledReturnsFalseByDefault(): void
    {
        $sut = $this->makeSut();

        static::assertFalse($sut->isScopeDisabled('my-scope'));
    }

    public function testItIsScopeDisabledReturnsTrueWhenGloballyDisabled(): void
    {
        $sut = $this->makeSut();
        $this->scopeContext->setAllScopesGloballyDisabled(true);

        static::assertTrue($sut->isScopeDisabled('my-scope'));
    }

    public function testItIsScopeDisabledReturnsTrueWhenLocallyDisabled(): void
    {
        $sut = $this->makeSut();
        $sut->setAllScopesLocallyDisabled(true);

        static::assertTrue($sut->isScopeDisabled('any-scope'));
    }

    public function testItIsScopeDisabledReturnsTrueForSpecificDisabledScope(): void
    {
        $sut = $this->makeSut();
        $sut->disableScope('my-scope');

        static::assertTrue($sut->isScopeDisabled('my-scope'));
    }

    public function testItIsScopeDisabledReturnsFalseForOtherScopesWhenOnlyOneIsDisabled(): void
    {
        $sut = $this->makeSut();
        $sut->disableScope('my-scope');

        static::assertFalse($sut->isScopeDisabled('other-scope'));
    }

    // =========================================================================
    // disableScope / resetScope
    // =========================================================================

    public function testItDisableScopeReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->disableScope('my-scope'));
    }

    public function testItResetScopeEnablesPreviouslyDisabledScope(): void
    {
        $sut = $this->makeSut();
        $sut->disableScope('my-scope');
        $sut->resetScope('my-scope');

        static::assertFalse($sut->isScopeDisabled('my-scope'));
    }

    public function testItResetScopeReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->resetScope('my-scope'));
    }

    public function testItResetScopeOnNonExistentScopeDoesNotThrow(): void
    {
        $sut = $this->makeSut();

        $sut->resetScope('non-existent-scope');

        static::assertFalse($sut->isScopeDisabled('non-existent-scope'));
    }

    // =========================================================================
    // setAllScopesLocallyDisabled
    // =========================================================================

    public function testItSetAllScopesLocallyDisabledReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setAllScopesLocallyDisabled(true));
    }

    public function testItSetAllScopesLocallyDisabledCanBeResetToFalse(): void
    {
        $sut = $this->makeSut();
        $sut->setAllScopesLocallyDisabled(true);
        $sut->setAllScopesLocallyDisabled(false);

        static::assertFalse($sut->isScopeDisabled('any-scope'));
    }

    // =========================================================================
    // setDefaultOnDisabledNotAllowed
    // =========================================================================

    public function testItSetDefaultOnDisabledNotAllowedReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setDefaultOnDisabledNotAllowed(fn() => null));
    }

    // =========================================================================
    // getOnDisableNotAllowed
    // =========================================================================

    public function testItGetOnDisableNotAllowedReturnsGlobalDefaultWhenNothingSet(): void
    {
        $globalDefault = fn() => null;
        $this->scopeContext->setDefaultOnDisabledNotAllowed($globalDefault);
        $sut = $this->makeSut();
        $sut->disableScope('my-scope');

        static::assertSame($globalDefault, $sut->getOnDisableNotAllowed('my-scope'));
    }

    public function testItGetOnDisableNotAllowedReturnsGloballyDisabledCallback(): void
    {
        $globalCallback = fn() => null;
        $this->scopeContext->setAllScopesGloballyDisabled(true, $globalCallback);
        $sut = $this->makeSut();

        static::assertSame($globalCallback, $sut->getOnDisableNotAllowed('my-scope'));
    }

    public function testItGetOnDisableNotAllowedReturnsLocallyDisabledCallback(): void
    {
        $localCallback = fn() => null;
        $sut = $this->makeSut();
        $sut->setAllScopesLocallyDisabled(true, $localCallback);

        static::assertSame($localCallback, $sut->getOnDisableNotAllowed('my-scope'));
    }

    public function testItGetOnDisableNotAllowedReturnsScopeSpecificCallback(): void
    {
        $scopeCallback = fn() => null;
        $sut = $this->makeSut();
        $sut->disableScope('my-scope', $scopeCallback);

        static::assertSame($scopeCallback, $sut->getOnDisableNotAllowed('my-scope'));
    }

    public function testItGetOnDisableNotAllowedPrefersGloballyDisabledCallbackOverLocal(): void
    {
        $globalCallback = fn() => null;
        $localCallback = fn() => true;
        $this->scopeContext->setAllScopesGloballyDisabled(true, $globalCallback);
        $sut = $this->makeSut();
        $sut->setAllScopesLocallyDisabled(true, $localCallback);

        static::assertSame($globalCallback, $sut->getOnDisableNotAllowed('my-scope'));
    }

    public function testItGetOnDisableNotAllowedPrefersLocallyDisabledCallbackOverScopeSpecific(): void
    {
        $localCallback = fn() => null;
        $scopeCallback = fn() => true;
        $sut = $this->makeSut();
        $sut->setAllScopesLocallyDisabled(true, $localCallback);
        $sut->disableScope('my-scope', $scopeCallback);

        static::assertSame($localCallback, $sut->getOnDisableNotAllowed('my-scope'));
    }

    // =========================================================================
    // getFullScopeKey
    // =========================================================================

    public function testItGetFullScopeKeyReturnsExpectedFormat(): void
    {
        $sut = $this->makeSut();

        static::assertSame(
            'context-aware-scope:ModelScopeContext:my-scope',
            $sut->getFullScopeKey('my-scope')
        );
    }

    // =========================================================================
    // runSandboxed
    // =========================================================================

    public function testItRunSandboxedRestoresStateAfterCallback(): void
    {
        $sut = $this->makeSut();

        $sut->runSandboxed(function (ModelScopeContext $ctx) {
            $ctx->disableScope('my-scope');
            static::assertTrue($ctx->isScopeDisabled('my-scope'));
        });

        static::assertFalse($sut->isScopeDisabled('my-scope'));
    }

    public function testItRunSandboxedRestoresStateOnException(): void
    {
        $sut = $this->makeSut();

        try {
            $sut->runSandboxed(function (ModelScopeContext $ctx) {
                $ctx->disableScope('my-scope');
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException) {
        }

        static::assertFalse($sut->isScopeDisabled('my-scope'));
    }

    public function testItRunSandboxedReturnsCallbackReturnValue(): void
    {
        $sut = $this->makeSut();

        $result = $sut->runSandboxed(fn() => 42);

        static::assertSame(42, $result);
    }

    public function testItRunSandboxedPassesModelContextAndGlobalContext(): void
    {
        $sut = $this->makeSut();
        $receivedModel = null;
        $receivedGlobal = null;

        $sut->runSandboxed(function (ModelScopeContext $model, ScopeContext $global) use (&$receivedModel, &$receivedGlobal) {
            $receivedModel = $model;
            $receivedGlobal = $global;
        });

        static::assertSame($sut, $receivedModel);
        static::assertSame($this->scopeContext, $receivedGlobal);
    }

    // =========================================================================
    // runSandboxedWithScopesLocallyDisabled
    // =========================================================================

    public function testItRunSandboxedWithScopesLocallyDisabledDisablesScopesInsideClosure(): void
    {
        $sut = $this->makeSut();
        $disabledInsideClosure = null;

        $sut->runSandboxedWithScopesLocallyDisabled(function (ModelScopeContext $ctx) use (&$disabledInsideClosure) {
            $disabledInsideClosure = $ctx->isScopeDisabled('any-scope');
        });

        static::assertTrue($disabledInsideClosure);
    }

    public function testItRunSandboxedWithScopesLocallyDisabledRestoresScopeStateAfterClosure(): void
    {
        $sut = $this->makeSut();

        $sut->runSandboxedWithScopesLocallyDisabled(fn() => null);

        static::assertFalse($sut->isScopeDisabled('any-scope'));
    }

    public function testItRunSandboxedWithScopesLocallyDisabledReturnsCallbackReturnValue(): void
    {
        $sut = $this->makeSut();

        $result = $sut->runSandboxedWithScopesLocallyDisabled(fn() => 'result');

        static::assertSame('result', $result);
    }
}
