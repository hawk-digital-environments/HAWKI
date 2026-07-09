<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent\Values;

use App\Services\ExternalContent\Values\ResolvedExternalImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolvedExternalImage::class)]
class ResolvedExternalImageTest extends TestCase
{
    public function testItConstructsWithRequiredFields(): void
    {
        $sut = new ResolvedExternalImage(
            content: 'binary-content',
            mimeType: 'image/png'
        );

        static::assertSame('binary-content', $sut->content);
        static::assertSame('image/png', $sut->mimeType);
        static::assertFalse($sut->isFallback);
    }

    public function testItConstructsAsFallback(): void
    {
        $sut = new ResolvedExternalImage(
            content: '<svg/>',
            mimeType: 'image/svg+xml',
            isFallback: true
        );

        static::assertSame('<svg/>', $sut->content);
        static::assertSame('image/svg+xml', $sut->mimeType);
        static::assertTrue($sut->isFallback);
    }
}
