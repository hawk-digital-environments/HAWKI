<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\Exceptions;

use App\Services\System\Http\Exceptions\HttpExceptionInterface;
use App\Services\System\Http\Exceptions\InvalidBaseUrlException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidBaseUrlException::class)]
class InvalidBaseUrlExceptionTest extends TestCase
{
    public function testItImplementsHttpExceptionInterface(): void
    {
        static::assertInstanceOf(HttpExceptionInterface::class, InvalidBaseUrlException::forBaseUrl('x'));
    }

    public function testItExtendsRuntimeException(): void
    {
        static::assertInstanceOf(\RuntimeException::class, InvalidBaseUrlException::forBaseUrl('x'));
    }

    // =========================================================================
    // forBaseUrl
    // =========================================================================

    public function testItCreatesForBaseUrl(): void
    {
        $sut = InvalidBaseUrlException::forBaseUrl('/not-absolute');

        static::assertInstanceOf(InvalidBaseUrlException::class, $sut);
        static::assertSame('Base URL "/not-absolute" is not a valid absolute URL.', $sut->getMessage());
    }
}
