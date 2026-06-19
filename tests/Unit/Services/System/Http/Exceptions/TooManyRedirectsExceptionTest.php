<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\Exceptions;

use App\Services\System\Http\Exceptions\HttpExceptionInterface;
use App\Services\System\Http\Exceptions\TooManyRedirectsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TooManyRedirectsException::class)]
class TooManyRedirectsExceptionTest extends TestCase
{
    public function testItImplementsHttpExceptionInterface(): void
    {
        static::assertInstanceOf(HttpExceptionInterface::class, TooManyRedirectsException::forUrl('http://example.com/', 5));
    }

    public function testItExtendsRuntimeException(): void
    {
        static::assertInstanceOf(\RuntimeException::class, TooManyRedirectsException::forUrl('http://example.com/', 5));
    }

    // =========================================================================
    // forUrl
    // =========================================================================

    public function testItCreatesForUrl(): void
    {
        $sut = TooManyRedirectsException::forUrl('https://example.com/page', 3);

        static::assertInstanceOf(TooManyRedirectsException::class, $sut);
        static::assertSame(
            'Failed to fetch "https://example.com/page" after 3 redirects.',
            $sut->getMessage()
        );
    }
}
