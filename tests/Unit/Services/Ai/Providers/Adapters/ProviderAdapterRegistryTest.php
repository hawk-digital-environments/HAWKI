<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ProviderAdapterAlreadyRegisteredException;
use App\Services\Ai\Exceptions\ProviderAdapterNotFoundException;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry;
use App\Utils\Lists\LazySingletonList;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Providers\Adapters\ProviderAdapterRegistryTestFixtures\ValidAdapterStub;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProviderAdapterRegistry::class)]
class ProviderAdapterRegistryTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a registry backed by a real LazySingletonList that resolves adapter
     * classes directly (no Laravel container needed).
     */
    private function makeRegistry(): ProviderAdapterRegistry
    {
        $instances = new LazySingletonList(
            keyGenerator: fn(array|null $args) => $args === null ? '__null__' : implode('_', $args),
            factory: function (array $args): ProviderAdapterInterface {
                [, $class] = $args;
                return new $class();
            }
        );

        return new ProviderAdapterRegistry($instances);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeRegistry();
        static::assertInstanceOf(ProviderAdapterRegistry::class, $sut);
    }

    // =========================================================================
    // declare
    // =========================================================================

    public function testItDeclareReturnsSelfForFluentChaining(): void
    {
        $sut = $this->makeRegistry();
        $result = $sut->declare('test', ValidAdapterStub::class);
        static::assertSame($sut, $result);
    }

    public function testItDeclareRegistersAdapter(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('test', ValidAdapterStub::class);
        static::assertTrue($sut->has('test'));
    }

    public function testItDeclareThrowsWhenKeyAlreadyRegistered(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('test', ValidAdapterStub::class);

        $this->expectException(ProviderAdapterAlreadyRegisteredException::class);
        $this->expectExceptionMessage(sprintf('Provider adapter with key "%s" is already registered.', 'test'));

        $sut->declare('test', ValidAdapterStub::class);
    }

    public function testItDeclareThrowsForNonExistentClass(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Adapter class "%s" does not exist.', 'NonExistent\ClassName'));

        $sut->declare('test', 'NonExistent\ClassName');
    }

    public function testItDeclareThrowsWhenClassDoesNotImplementInterface(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Adapter class "%s" must implement %s.',
            \stdClass::class,
            ProviderAdapterInterface::class
        ));

        $sut->declare('test', \stdClass::class);
    }

    // =========================================================================
    // has
    // =========================================================================

    public function testItHasReturnsFalseForUnregisteredKey(): void
    {
        $sut = $this->makeRegistry();
        static::assertFalse($sut->has('unknown'));
    }

    public function testItHasReturnsTrueAfterDeclare(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('my_adapter', ValidAdapterStub::class);
        static::assertTrue($sut->has('my_adapter'));
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testItGetReturnsAdapterInstance(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('stub', ValidAdapterStub::class);

        $adapter = $sut->get('stub');

        static::assertInstanceOf(ValidAdapterStub::class, $adapter);
    }

    public function testItGetReturnsSameInstanceOnSubsequentCalls(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('stub', ValidAdapterStub::class);

        $first  = $sut->get('stub');
        $second = $sut->get('stub');

        static::assertSame($first, $second);
    }

    public function testItGetThrowsForUnregisteredKey(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(ProviderAdapterNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Provider adapter with key "%s" is not registered.', 'missing'));

        $sut->get('missing');
    }

    // =========================================================================
    // getForProvider
    // =========================================================================

    public function testItGetForProviderReturnsAdapterMatchingProviderAdapterKey(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('openai', ValidAdapterStub::class);

        $provider = new AiProvider(['adapter_key' => 'openai']);
        $adapter  = $sut->getForProvider($provider);

        static::assertInstanceOf(ValidAdapterStub::class, $adapter);
    }

    public function testItGetForProviderThrowsWhenAdapterKeyNotRegistered(): void
    {
        $sut      = $this->makeRegistry();
        $provider = new AiProvider(['adapter_key' => 'unknown_key']);

        $this->expectException(ProviderAdapterNotFoundException::class);

        $sut->getForProvider($provider);
    }

    // =========================================================================
    // getForModel
    // =========================================================================

    public function testItGetForModelReturnsAdapterForModelsProvider(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('anthropic', ValidAdapterStub::class);

        $provider = new AiProvider(['adapter_key' => 'anthropic']);

        $model = $this->createMock(AiModel::class);
        $model->expects(static::once())
            ->method('__get')
            ->with('provider')
            ->willReturn($provider);

        $adapter = $sut->getForModel($model);

        static::assertInstanceOf(ValidAdapterStub::class, $adapter);
    }

    // =========================================================================
    // remove
    // =========================================================================

    public function testItRemoveIsNoOpForUnregisteredKey(): void
    {
        $sut = $this->makeRegistry();

        // Must not throw
        $sut->remove('nonexistent');

        static::assertFalse($sut->has('nonexistent'));
    }

    public function testItRemoveClearsInstanceCacheSoNextGetCreatesNewInstance(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare('stub', ValidAdapterStub::class);

        $first = $sut->get('stub');
        $sut->remove('stub');

        // Re-declare and fetch — should be a fresh instance
        $sut->declare('stub', ValidAdapterStub::class);
        $second = $sut->get('stub');

        static::assertNotSame($first, $second);
    }
}
