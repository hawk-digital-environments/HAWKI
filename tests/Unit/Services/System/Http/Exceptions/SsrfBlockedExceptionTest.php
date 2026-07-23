<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\Exceptions;

use App\Services\System\Http\Exceptions\HttpExceptionInterface;
use App\Services\System\Http\Exceptions\SsrfBlockedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SsrfBlockedException::class)]
class SsrfBlockedExceptionTest extends TestCase
{
    public function testItImplementsHttpExceptionInterface(): void
    {
        static::assertInstanceOf(HttpExceptionInterface::class, SsrfBlockedException::malformedUrl('x'));
    }

    public function testItExtendsRuntimeException(): void
    {
        static::assertInstanceOf(\RuntimeException::class, SsrfBlockedException::malformedUrl('x'));
    }

    // =========================================================================
    // malformedUrl
    // =========================================================================

    public function testItCreatesMalformedUrl(): void
    {
        $sut = SsrfBlockedException::malformedUrl('not-a-url');

        static::assertInstanceOf(SsrfBlockedException::class, $sut);
        static::assertSame('Malformed URL: "not-a-url".', $sut->getMessage());
    }

    // =========================================================================
    // unsupportedScheme
    // =========================================================================

    public function testItCreatesUnsupportedScheme(): void
    {
        $sut = SsrfBlockedException::unsupportedScheme('ftp');

        static::assertInstanceOf(SsrfBlockedException::class, $sut);
        static::assertSame('Only http and https URLs are allowed, got: "ftp".', $sut->getMessage());
    }

    // =========================================================================
    // credentialsInUrl
    // =========================================================================

    public function testItCreatesCredentialsInUrl(): void
    {
        $sut = SsrfBlockedException::credentialsInUrl();

        static::assertInstanceOf(SsrfBlockedException::class, $sut);
        static::assertSame('Credentials in URL are not allowed.', $sut->getMessage());
    }

    // =========================================================================
    // nonPublicAddress
    // =========================================================================

    public function testItCreatesNonPublicAddress(): void
    {
        $sut = SsrfBlockedException::nonPublicAddress('192.168.1.1');

        static::assertInstanceOf(SsrfBlockedException::class, $sut);
        static::assertSame('URL host "192.168.1.1" resolves to a non-public address.', $sut->getMessage());
    }

    // =========================================================================
    // unresolvableHost
    // =========================================================================

    public function testItCreatesUnresolvableHost(): void
    {
        $sut = SsrfBlockedException::unresolvableHost('no-such-host.invalid');

        static::assertInstanceOf(SsrfBlockedException::class, $sut);
        static::assertSame('Could not resolve host: "no-such-host.invalid".', $sut->getMessage());
    }
}
