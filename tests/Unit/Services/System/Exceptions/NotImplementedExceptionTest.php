<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Exceptions;

use App\Services\System\Exceptions\NotImplementedException;
use App\Services\System\Exceptions\SystemExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NotImplementedException::class)]
class NotImplementedExceptionTest extends TestCase
{
    public function testItIsLogicException(): void
    {
        $sut = NotImplementedException::forReason('test feature');

        static::assertInstanceOf(\LogicException::class, $sut);
    }

    public function testItImplementsSystemExceptionInterface(): void
    {
        $sut = NotImplementedException::forReason('test feature');

        static::assertInstanceOf(SystemExceptionInterface::class, $sut);
    }

    public function testItForReasonContainsReason(): void
    {
        $sut = NotImplementedException::forReason('my missing feature');

        static::assertStringContainsString('my missing feature', $sut->getMessage());
    }

    public function testItForReasonMatchesExpectedMessage(): void
    {
        $sut = NotImplementedException::forReason('my missing feature');

        static::assertSame('Not implemented: my missing feature', $sut->getMessage());
    }
}
