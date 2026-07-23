<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Health\Exception;

use App\Services\System\Health\Exception\HealthcheckFailedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HealthcheckFailedException::class)]
class HealthcheckFailedExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsRuntimeException(): void
    {
        $sut = new HealthcheckFailedException('Something went wrong');

        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItExposesMessage(): void
    {
        $sut = new HealthcheckFailedException('Cache read/write verification failed');

        static::assertSame('Cache read/write verification failed', $sut->getMessage());
    }

    public function testItCanBeConstructedWithCode(): void
    {
        $sut = new HealthcheckFailedException('msg', 42);

        static::assertSame(42, $sut->getCode());
    }
}
