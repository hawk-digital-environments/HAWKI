<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\ProviderIconService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(ProviderIconService::class)]
class ProviderIconServiceTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';

    public function testCatalogueIsCachedFilteredAndRestrictedToAiAndTrustedRoutes(): void
    {
        Cache::forget('admin.svgl.ai.v2');
        Http::preventStrayRequests();
        Http::fake(['https://api.svgl.app/category/AI' => Http::response([
            ['id' => 1, 'title' => 'Example AI', 'category' => ['Software', 'AI'], 'route' => 'https://svgl.app/library/example.svg'],
            ['id' => 2, 'title' => 'Other', 'category' => 'Software', 'route' => 'https://svgl.app/other.svg'],
            ['id' => 3, 'title' => 'External', 'category' => 'AI', 'route' => 'https://example.org/icon.svg'],
        ])]);
        $service = app(ProviderIconService::class);
        self::assertSame([1], array_column($service->search(), 'id'));
        self::assertCount(1, $service->search());
        Http::assertSentCount(1);
    }

    public function testSelectionStoresBothSvgVariantsAndUnchangedValuesNeedNoNetwork(): void
    {
        Cache::forget('admin.svgl.ai.v2');
        Http::preventStrayRequests();
        Cache::forget('admin.svgl.all.v2');
        Http::fake([
            'https://api.svgl.app' => Http::response([
                ['id' => 1, 'title' => 'Example', 'category' => 'AI', 'route' => [
                    'light' => 'https://svgl.app/library/light.svg', 'dark' => 'https://svgl.app/library/dark.svg',
                ]],
            ]),
            'https://svgl.app/library/*.svg' => Http::response(self::SVG),
        ]);
        $service = app(ProviderIconService::class);
        $icon = $service->resolve(['source' => 'svgl', 'svgl_id' => 1, 'title' => 'Example'], null);
        self::assertSame(self::SVG, $icon['svg']);
        self::assertSame(self::SVG, $icon['svg_dark']);
        self::assertSame($icon, $service->resolve(array_reverse($icon, true), $icon));
        self::assertNull($service->resolve(null, $icon));
        Http::assertSentCount(3);
    }

    public function testSearchIncludesOtherCategoriesAndClearingItRestoresAi(): void
    {
        Cache::forget('admin.svgl.ai.v2');
        Cache::forget('admin.svgl.all.v2');
        Http::preventStrayRequests();
        $ai = ['id' => 1, 'title' => 'Example AI', 'category' => 'AI', 'route' => 'https://svgl.app/library/ai.svg'];
        $other = ['id' => 2, 'title' => 'Example Browser', 'category' => 'Browser', 'route' => 'https://svgl.app/library/browser.svg'];
        Http::fake([
            'https://api.svgl.app/category/AI' => Http::response([$ai]),
            'https://api.svgl.app' => Http::response([$ai, $other]),
            'https://svgl.app/library/browser.svg' => Http::response(self::SVG),
        ]);
        $service = app(ProviderIconService::class);
        self::assertSame([1], array_column($service->search(), 'id'));
        self::assertSame([2], array_column($service->search('BROWSER'), 'id'));
        self::assertSame([1, 2], array_column($service->search('example'), 'id'));
        self::assertSame([], $service->search('missing'));
        self::assertSame([1], array_column($service->search('  '), 'id'));
        $icon = $service->resolve(['source' => 'svgl', 'svgl_id' => 2, 'title' => 'Example Browser'], null);
        self::assertSame(self::SVG, $icon['svg']);
        Http::assertSentCount(3);
    }

    public function testArbitraryIconIdsCannotBeImported(): void
    {
        Cache::put('admin.svgl.all.v2', [], 60);
        Http::preventStrayRequests();
        $this->expectException(ValidationException::class);
        app(ProviderIconService::class)->resolve(['source' => 'svgl', 'svgl_id' => 123, 'title' => 'Other', 'light' => 'http://127.0.0.1'], null);
    }

    #[DataProvider('unsafeSvg')]
    public function testRejectsUnsafeAndMalformedSvg(string $svg): void
    {
        $this->expectException(ValidationException::class);
        app(ProviderIconService::class)->validateSvg($svg);
    }

    public static function unsafeSvg(): iterable
    {
        foreach ([
            '<script>alert(1)</script>',
            '<image href="data:image/png;base64,bm90LWFuLWltYWdl"/>',
            '<path onload="alert(1)"/>',
            '<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">HTML</div></foreignObject>',
            '<use href="https://example.org/icon.svg#x"/>',
            '<path fill="url(https://example.org/icon.svg)"/>',
            '<style>@import url(https://example.org/style.css);</style>',
            '<path style="fill:url(https://example.org/icon.svg)"/>',
            '<path style="fill:expression(alert(1))"/>',
            '<style>.x {fill:url(https://example.org/icon.svg)}</style>',
            '<?xml-stylesheet href="https://example.org/style.css"?>',
            '<animate attributeName="href" values="javascript:alert(1)"/>',
        ] as $content) {
            yield ['<svg xmlns="http://www.w3.org/2000/svg">' . $content . '</svg>'];
        }

        yield ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'];

        yield ['<svg'];

        yield ['<html/>'];

        yield [str_repeat(' ', ProviderIconService::MAX_BYTES) . self::SVG];
    }

    public function testKeepsGradientsMasksAndLocalReferences(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="a"><stop offset="0" stop-color="#fff"/></linearGradient></defs><path d="M0 0" fill="url(#a)"/></svg>';
        self::assertSame($svg, app(ProviderIconService::class)->validateSvg($svg));
    }

    #[DataProvider('strokeStyles')]
    public function testKeepsStaticStrokeStyles(string $svg): void
    {
        self::assertSame($svg, app(ProviderIconService::class)->validateSvg($svg));
    }

    public static function strokeStyles(): iterable
    {
        foreach (['stroke-miterlimit:10', 'stroke-dasharray:none', 'stroke-dasharray:4,2', 'stroke-dashoffset:-1.5'] as $style) {
            yield 'inline ' . $style => ['<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24" style="' . $style . '"/></svg>'];

            yield 'stylesheet ' . $style => ['<svg xmlns="http://www.w3.org/2000/svg"><style>.line{' . $style . '}</style><path class="line" d="M0 0h24"/></svg>'];
        }
    }
}
