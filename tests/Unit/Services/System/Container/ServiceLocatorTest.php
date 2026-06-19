<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Container;

use App\Services\System\Container\Exceptions\ContainerExceptionInterface;
use App\Services\System\Container\Exceptions\ServiceLocatorException;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ServiceLocator::class)]
#[CoversClass(ServiceLocatorException::class)]
class ServiceLocatorTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new ServiceLocator();

        static::assertInstanceOf(ServiceLocator::class, $sut);
    }

    public function testItConstructsWithContainer(): void
    {
        $sut = new ServiceLocator(new Container());

        static::assertInstanceOf(ServiceLocator::class, $sut);
    }

    // =========================================================================
    // set / get
    // =========================================================================

    public function testItGetReturnsLocalServiceWhenSet(): void
    {
        $service = new \stdClass();
        $sut = new ServiceLocator();
        $sut->set('my.service', $service);

        static::assertSame($service, $sut->get('my.service'));
    }

    public function testItGetPrefersLocalServiceOverContainer(): void
    {
        $container = new Container();
        $containerService = new \stdClass();
        $container->instance('my.service', $containerService);

        $localService = new \stdClass();
        $sut = new ServiceLocator($container);
        $sut->set('my.service', $localService);

        static::assertSame($localService, $sut->get('my.service'));
    }

    public function testItGetFallsBackToContainerWhenServiceNotLocal(): void
    {
        $container = new Container();
        $service = new \stdClass();
        $container->instance('my.service', $service);

        $sut = new ServiceLocator($container);

        static::assertSame($service, $sut->get('my.service'));
    }

    public function testItGetThrowsWhenServiceNotFoundAndNoContainer(): void
    {
        $sut = new ServiceLocator();

        $this->expectException(ServiceLocatorException::class);
        $this->expectExceptionMessage(sprintf(
            'Service with id "%s" not found in ServiceLocator and no container available to resolve it.',
            'my.service',
        ));

        $sut->get('my.service');
    }

    public function testItGetThrowsImplementsContainerExceptionInterface(): void
    {
        $sut = new ServiceLocator();

        try {
            $sut->get('my.service');
            static::fail('Expected ServiceLocatorException to be thrown.');
        } catch (ServiceLocatorException $e) {
            static::assertInstanceOf(ContainerExceptionInterface::class, $e);
        }
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = new ServiceLocator();

        static::assertSame($sut, $sut->set('my.service', new \stdClass()));
    }

    // =========================================================================
    // setContainer
    // =========================================================================

    public function testItSetContainerReturnsSelf(): void
    {
        $sut = new ServiceLocator();

        static::assertSame($sut, $sut->setContainer(null));
    }

    public function testItSetContainerNullDisablesContainerFallback(): void
    {
        $container = new Container();
        $container->instance('my.service', new \stdClass());

        $sut = new ServiceLocator($container);
        $sut->setContainer(null);

        $this->expectException(ServiceLocatorException::class);
        $sut->get('my.service');
    }

    public function testItSetContainerOverridesConstructorContainer(): void
    {
        $newContainer = new Container();
        $service = new \stdClass();
        $newContainer->instance('my.service', $service);

        $sut = new ServiceLocator(new Container());
        $sut->setContainer($newContainer);

        static::assertSame($service, $sut->get('my.service'));
    }

    // =========================================================================
    // setCallParams / call
    // =========================================================================

    public function testItSetCallParamsReturnsSelf(): void
    {
        $sut = new ServiceLocator();

        static::assertSame($sut, $sut->setCallParams('my.action', []));
    }

    public function testItCallExecutesCallbackWithPreRegisteredParams(): void
    {
        $sut = new ServiceLocator();
        $sut->setCallParams('my.action', ['foo', 'bar']);

        $received = null;
        $sut->call('my.action', function (string $a, string $b) use (&$received): void {
            $received = [$a, $b];
        });

        static::assertSame(['foo', 'bar'], $received);
    }

    public function testItCallReturnsCallbackResult(): void
    {
        $sut = new ServiceLocator();
        $sut->setCallParams('my.action', [21]);

        $result = $sut->call('my.action', fn(int $n): int => $n * 2);

        static::assertSame(42, $result);
    }

    public function testItCallFallsBackToContainerWhenNoPreRegisteredParams(): void
    {
        $sut = new ServiceLocator(new Container());

        $wasCalled = false;
        $sut->call('my.action', function () use (&$wasCalled): void {
            $wasCalled = true;
        });

        static::assertTrue($wasCalled);
    }

    public function testItCallPassesParametersToContainerCall(): void
    {
        $sut = new ServiceLocator(new Container());

        $received = null;
        $sut->call('my.action', function (string $value) use (&$received): void {
            $received = $value;
        }, ['value' => 'hello']);

        static::assertSame('hello', $received);
    }

    public function testItCallThrowsWhenNoPreRegisteredParamsAndNoContainer(): void
    {
        $sut = new ServiceLocator();

        $this->expectException(ServiceLocatorException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to execute callback with id "%s". There are neither defined execution params nor an application instance available.',
            'my.action',
        ));

        $sut->call('my.action', function (): void {});
    }

    public function testItCallAcceptsArrayExecutionId(): void
    {
        $sut = new ServiceLocator();
        $sut->setCallParams('my.nested.action', ['hello']);

        $received = null;
        $sut->call(['my', 'nested', 'action'], function (string $val) use (&$received): void {
            $received = $val;
        });

        static::assertSame('hello', $received);
    }

    public function testItCallArrayExecutionIdJoinedWithDots(): void
    {
        $sut = new ServiceLocator();

        $this->expectException(ServiceLocatorException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to execute callback with id "%s". There are neither defined execution params nor an application instance available.',
            'a.b.c',
        ));

        $sut->call(['a', 'b', 'c'], function (): void {});
    }
}
