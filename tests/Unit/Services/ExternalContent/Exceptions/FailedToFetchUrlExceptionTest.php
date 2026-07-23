<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent\Exceptions;

use App\Services\ExternalContent\Exceptions\ExternalContentExceptionInterface;
use App\Services\ExternalContent\Exceptions\FailedToFetchUrlException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FailedToFetchUrlException::class)]
class FailedToFetchUrlExceptionTest extends TestCase
{
    public function testItImplementsExternalContentExceptionInterface(): void
    {
        static::assertInstanceOf(
            ExternalContentExceptionInterface::class,
            FailedToFetchUrlException::forUrl('https://example.com/')
        );
    }

    public function testItExtendsRuntimeException(): void
    {
        static::assertInstanceOf(
            \RuntimeException::class,
            FailedToFetchUrlException::forUrl('https://example.com/')
        );
    }

    // =========================================================================
    // forUrl
    // =========================================================================

    public function testItCreatesForUrl(): void
    {
        $sut = FailedToFetchUrlException::forUrl('https://example.com/');

        static::assertInstanceOf(FailedToFetchUrlException::class, $sut);
        static::assertSame(
            'Failed to fetch external URL "https://example.com/".',
            $sut->getMessage()
        );
    }
}
