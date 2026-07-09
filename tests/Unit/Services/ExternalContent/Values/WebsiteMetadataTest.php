<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent\Values;

use App\Services\ExternalContent\Values\WebsiteMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebsiteMetadata::class)]
class WebsiteMetadataTest extends TestCase
{
    private function makeSut(array $overrides = []): WebsiteMetadata
    {
        return new WebsiteMetadata(
            url: $overrides['url'] ?? 'https://example.com/',
            domain: $overrides['domain'] ?? 'example.com',
            title: $overrides['title'] ?? 'Example',
            description: $overrides['description'] ?? null,
            image: $overrides['image'] ?? null,
            favicon: $overrides['favicon'] ?? null,
            isFallback: $overrides['isFallback'] ?? false,
        );
    }

    public function testItConstructsWithDefaults(): void
    {
        $sut = $this->makeSut();

        static::assertSame('https://example.com/', $sut->url);
        static::assertSame('example.com', $sut->domain);
        static::assertSame('Example', $sut->title);
        static::assertNull($sut->description);
        static::assertNull($sut->image);
        static::assertNull($sut->favicon);
        static::assertFalse($sut->isFallback);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItConvertsToArray(): void
    {
        $sut = $this->makeSut([
            'description' => 'A description',
            'image' => 'https://hawki.test/image?url=https%3A%2F%2Fexample.com%2Fimg.jpg',
            'favicon' => 'https://hawki.test/favicon?url=https%3A%2F%2Fexample.com',
            'isFallback' => true,
        ]);

        static::assertSame([
            'url' => 'https://example.com/',
            'domain' => 'example.com',
            'title' => 'Example',
            'description' => 'A description',
            'image' => 'https://hawki.test/image?url=https%3A%2F%2Fexample.com%2Fimg.jpg',
            'favicon' => 'https://hawki.test/favicon?url=https%3A%2F%2Fexample.com',
            'isFallback' => true,
        ], $sut->toArray());
    }

    public function testItConvertsToArrayWithNullOptionalFields(): void
    {
        $sut = $this->makeSut();

        $array = $sut->toArray();

        static::assertNull($array['description']);
        static::assertNull($array['image']);
        static::assertNull($array['favicon']);
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesIdenticallyToToArray(): void
    {
        $sut = $this->makeSut(['description' => 'Some description']);

        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }
}
