<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Http;

use App\Services\System\Http\Exceptions\InvalidBaseUrlException;
use App\Services\System\Http\UrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

#[CoversClass(UrlResolver::class)]
class UrlResolverTest extends TestCase
{
    // =========================================================================
    // resolve
    // =========================================================================

    #[DataProvider('provideTestItResolvesUrlsData')]
    public function testItResolvesUrls(string $base, string $relative, string $expected): void
    {
        static::assertSame($expected, UrlResolver::resolve($base, $relative));
    }

    public static function provideTestItResolvesUrlsData(): iterable
    {
        yield 'absolute url is returned unchanged' => [
            'http://example.com/page',
            'https://other.com/resource',
            'https://other.com/resource',
        ];

        yield 'protocol-relative url inherits https scheme from base' => [
            'https://example.com/page',
            '//cdn.example.com/asset.js',
            'https://cdn.example.com/asset.js',
        ];

        yield 'protocol-relative url inherits http scheme from base' => [
            'http://example.com/page',
            '//cdn.example.com/style.css',
            'http://cdn.example.com/style.css',
        ];

        yield 'absolute path uses base origin' => [
            'https://example.com/some/deep/path.html',
            '/images/photo.jpg',
            'https://example.com/images/photo.jpg',
        ];

        yield 'absolute path preserves port' => [
            'http://example.com:8080/app/page',
            '/other',
            'http://example.com:8080/other',
        ];

        yield 'relative path resolved against base directory' => [
            'https://example.com/blog/post.html',
            'image.jpg',
            'https://example.com/blog/image.jpg',
        ];

        yield 'relative path with deeper base directory' => [
            'https://example.com/a/b/c.html',
            'sibling.html',
            'https://example.com/a/b/sibling.html',
        ];

        yield 'relative path with root-only base path' => [
            'https://example.com/',
            'page.html',
            'https://example.com/page.html',
        ];
    }

    // =========================================================================
    // invalid base URL
    // =========================================================================

    public function testItThrowsForBaseUrlWithNoHost(): void
    {
        $this->expectException(InvalidBaseUrlException::class);
        $this->expectExceptionMessage('Base URL "/relative-path" is not a valid absolute URL.');

        UrlResolver::resolve('/relative-path', 'page.html');
    }

    public function testItThrowsForUnparsableBaseUrl(): void
    {
        $this->expectException(InvalidBaseUrlException::class);
        $this->expectExceptionMessage('Base URL "not a url at all" is not a valid absolute URL.');

        UrlResolver::resolve('not a url at all', 'page.html');
    }

    public function testItDoesNotThrowForAbsoluteRelativeUrlEvenWithInvalidBase(): void
    {
        // When $relativeUrl is already absolute the base URL is never inspected.
        $result = UrlResolver::resolve('invalid-base', 'https://example.com/page.html');

        static::assertSame('https://example.com/page.html', $result);
    }

    public static function urlsToReturnBase(): iterable
    {
        yield [
            'http://example.com/page',
            'http://example.com'
        ];
                yield [
            'example.com/page',
            'example.com'
        ];
        yield [
            'http://example.com/page/nested/with?=123&swe',
            'http://example.com'
        ];
    }

    #[DataProvider('urlsToReturnBase')]
    public function testItCreatesBAse(string $givenUrl, string $expectedBase): void
    {
        static::assertSame($expectedBase, UrlResolver::baseUrl($givenUrl));
    }
}
