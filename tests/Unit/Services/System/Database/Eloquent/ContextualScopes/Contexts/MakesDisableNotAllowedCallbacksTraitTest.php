<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTraitTestFixtures\MakesDisableNotAllowedCallbacksProxy;

#[CoversTrait(MakesDisableNotAllowedCallbacksTrait::class)]
class MakesDisableNotAllowedCallbacksTraitTest extends TestCase
{
    private MakesDisableNotAllowedCallbacksProxy $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new MakesDisableNotAllowedCallbacksProxy();
    }

    // =========================================================================
    // makeDisableNotAllowedIgnore
    // =========================================================================

    public function testItMakeDisableNotAllowedIgnoreReturnsClosure(): void
    {
        static::assertInstanceOf(\Closure::class, $this->sut->ignore());
    }

    public function testItMakeDisableNotAllowedIgnoreReturnsFalse(): void
    {
        $callback = $this->sut->ignore();

        static::assertFalse($callback());
    }

    // =========================================================================
    // makeDisableNotAllowedForceDisable
    // =========================================================================

    public function testItMakeDisableNotAllowedForceDisableReturnsClosure(): void
    {
        static::assertInstanceOf(\Closure::class, $this->sut->forceDisable());
    }

    public function testItMakeDisableNotAllowedForceDisableReturnsTrue(): void
    {
        $callback = $this->sut->forceDisable();

        static::assertTrue($callback());
    }

    // =========================================================================
    // makeDisableNotAllowedThrowException
    // =========================================================================

    public function testItMakeDisableNotAllowedThrowExceptionReturnsClosure(): void
    {
        static::assertInstanceOf(\Closure::class, $this->sut->throwException());
    }

    public function testItMakeDisableNotAllowedThrowExceptionAbortsWith403(): void
    {
        $scopeContext = new ScopeContext(fn() => true);
        $modelContext = $scopeContext->getModelContext('App\\Models\\Test');

        $callback = $this->sut->throwException();

        $this->expectException(HttpException::class);
        $callback('my-scope', $modelContext);
    }

    public function testItMakeDisableNotAllowedThrowExceptionMessageContainsScopeKey(): void
    {
        $scopeContext = new ScopeContext(fn() => true);
        $modelContext = $scopeContext->getModelContext('App\\Models\\Test');

        $callback = $this->sut->throwException();

        try {
            $callback('active_filter', $modelContext);
            static::fail('Expected HttpException to be thrown.');
        } catch (HttpException $e) {
            static::assertStringContainsString('active_filter', $e->getMessage());
        }
    }

    public function testItMakeDisableNotAllowedThrowExceptionMessageContainsModelBasename(): void
    {
        $scopeContext = new ScopeContext(fn() => true);
        $modelContext = $scopeContext->getModelContext('App\\Models\\MySpecialModel');

        $callback = $this->sut->throwException();

        try {
            $callback('active_filter', $modelContext);
            static::fail('Expected HttpException to be thrown.');
        } catch (HttpException $e) {
            static::assertStringContainsString('MySpecialModel', $e->getMessage());
        }
    }
}
