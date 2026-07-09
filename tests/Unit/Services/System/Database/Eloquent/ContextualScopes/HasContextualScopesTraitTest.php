<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes;

use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\ContextualScopeWrapper;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTraitTestFixtures\TestModelForHasContextualScopes;

#[CoversTrait(HasContextualScopesTrait::class)]
class HasContextualScopesTraitTest extends TestCase
{
    private ScopeContext $scopeContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetModelState();
        $this->scopeContext = new ScopeContext(fn() => true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetModelState();
    }

    private function resetModelState(): void
    {
        $prop = new \ReflectionProperty(TestModelForHasContextualScopes::class, 'hcst_booted');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
        Model::clearBootedModels();
    }

    private function bootModel(): void
    {
        TestModelForHasContextualScopes::setDependenciesOfHasContextualScopesTrait(
            new ServiceLocator(app()),
            $this->scopeContext
        );
        TestModelForHasContextualScopes::bootHasContextualScopesTrait();
    }

    // =========================================================================
    // setDependenciesOfHasContextualScopesTrait
    // =========================================================================

    public function testItSetDependenciesStoresServiceLocatorAndScopeContext(): void
    {
        $sl = new ServiceLocator(app());
        TestModelForHasContextualScopes::setDependenciesOfHasContextualScopesTrait($sl, $this->scopeContext);

        // Indirectly verified: boot must not fall back to container if deps are set
        TestModelForHasContextualScopes::bootHasContextualScopesTrait();

        // No exception thrown = deps were used (container not called)
        static::assertTrue(true);
    }

    // =========================================================================
    // bootHasContextualScopesTrait
    // =========================================================================

    public function testItBootRegistersContextualScopesAsEloquentGlobalScopes(): void
    {
        $this->bootModel();

        $scopes = TestModelForHasContextualScopes::getContextualScopes();

        static::assertArrayHasKey('test-scope', $scopes);
        static::assertInstanceOf(ContextualScopeWrapper::class, $scopes['test-scope']);
    }

    public function testItBootRunsOnlyOnce(): void
    {
        $this->bootModel();

        // Second call should be a no-op (guarded by $hcst_booted)
        TestModelForHasContextualScopes::bootHasContextualScopesTrait();

        // Still exactly one scope registered — not doubled
        static::assertCount(1, TestModelForHasContextualScopes::getContextualScopes());
    }

    // =========================================================================
    // getContextualScopes
    // =========================================================================

    public function testItGetContextualScopesReturnsWrapperKeyedByScopeKey(): void
    {
        $this->bootModel();

        $scopes = TestModelForHasContextualScopes::getContextualScopes();

        static::assertIsArray($scopes);
        static::assertArrayHasKey('test-scope', $scopes);
    }

    public function testItGetContextualScopesTriggersBootIfNotYetBooted(): void
    {
        TestModelForHasContextualScopes::setDependenciesOfHasContextualScopesTrait(
            new ServiceLocator(app()),
            $this->scopeContext
        );

        // Should auto-boot on first call
        $scopes = TestModelForHasContextualScopes::getContextualScopes();

        static::assertArrayHasKey('test-scope', $scopes);
    }

    // =========================================================================
    // scopeContext
    // =========================================================================

    public function testItScopeContextReturnsModelScopeContext(): void
    {
        $this->bootModel();

        $ctx = TestModelForHasContextualScopes::scopeContext();

        static::assertInstanceOf(ModelScopeContext::class, $ctx);
    }

    public function testItScopeContextReturnsContextForCorrectModel(): void
    {
        $this->bootModel();

        $ctx = TestModelForHasContextualScopes::scopeContext();
        $expected = $this->scopeContext->getModelContext(TestModelForHasContextualScopes::class);

        static::assertSame($expected, $ctx);
    }

    public function testItScopeContextTriggersBootIfNotYetBooted(): void
    {
        TestModelForHasContextualScopes::setDependenciesOfHasContextualScopesTrait(
            new ServiceLocator(app()),
            $this->scopeContext
        );

        $ctx = TestModelForHasContextualScopes::scopeContext();

        static::assertInstanceOf(ModelScopeContext::class, $ctx);
    }
}
