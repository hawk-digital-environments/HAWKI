<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\View;

use App\Services\Frontend\View\BrandTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandTheme::class)]
class BrandThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Start every test from the built-in theme: the application boots with whatever branding
        // the local database holds, which must not leak into these expectations.
        config(['hawki.branding' => [
            'accent_color' => null,
            'corner_style' => 'round',
            'logo_height' => null,
            'status_colors' => BrandTheme::STATUS_COLORS,
            'font' => null,
            'font_500' => null,
            'font_600' => null,
            'font_italic' => null,
            'font_500_italic' => null,
            'font_600_italic' => null,
        ]]);
    }

    public function testItRendersNothingForTheDefaultTheme(): void
    {
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => null]);

        static::assertSame('', (new BrandTheme())->render());
    }

    public function testTheDefaultAccentMapsOntoTheDefaultTokens(): void
    {
        // The default brand color (config/hawki.php) must reproduce the built-in ramp exactly.
        static::assertSame(['hue' => 265.0, 'chroma' => 1.0], BrandTheme::accentTokens('#1139d7'));
    }

    public function testAGreyBrandColorYieldsANeutralRamp(): void
    {
        static::assertEqualsWithDelta(0, BrandTheme::accentTokens('#808080')['chroma'], 0.001);
        static::assertEqualsWithDelta(0, BrandTheme::accentTokens('#000000')['chroma'], 0.001);
    }

    public function testItRendersTheAccentAndCornerTokensUnlayered(): void
    {
        config(['hawki.branding.accent_color' => '#FF0000', 'hawki.branding.corner_style' => 'square']);

        $html = (new BrandTheme())->render();

        static::assertStringStartsWith('<style>:root{--accent-hue:29.2;--accent-chroma:1;', $html);
        static::assertStringContainsString('--corner-lg:0', $html);
        // Units throughout: the control radii are used in calc().
        static::assertStringContainsString('--corner-control:0px;--corner-control-sm:0px', $html);
        static::assertStringNotContainsString('@layer', $html);
    }

    public function testItRendersTheLogoHeightInRemAndIgnoresValuesOutOfRange(): void
    {
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => 36]);
        static::assertSame('<style>:root{--logo-height:2.25rem}</style>', (new BrandTheme())->render());

        config(['hawki.branding.logo_height' => 400]);
        static::assertSame('', (new BrandTheme())->render());
    }

    public function testStatusColorsAreRenderedOnlyWhereTheyDifferFromTheBuiltInOnes(): void
    {
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => null,
            'hawki.branding.status_colors' => BrandTheme::STATUS_COLORS]);
        static::assertSame('', (new BrandTheme())->render());

        config(['hawki.branding.status_colors.error' => ' OKLCH( 40%  0.2 10 ) ', 'hawki.branding.status_colors.info' => 'oklch(40% 0.2 10);}body{display:none']);
        static::assertSame('<style>:root{--color-error:oklch(40% 0.2 10)}</style>', (new BrandTheme())->render());
    }

    public function testOklchAcceptsPlainInRangeValuesOnly(): void
    {
        static::assertSame('oklch(52% 0.21 25)', BrandTheme::oklch('oklch(52% 0.21 25)'));
        static::assertSame('oklch(50.5% 0.125 359.5)', BrandTheme::oklch('oklch(50.5% 0.125 359.5)'));

        foreach (['oklch(101% 0.2 10)', 'oklch(50% 0.5 10)', 'oklch(50% 0.2 361)', 'oklch(50% 0.2 10 / 0.5)', 'oklch(50% var(--x) 10)', 'red', '#ff0000', '', null, 12] as $value) {
            static::assertNull(BrandTheme::oklch($value));
        }
    }

    public function testFontFacesFollowTheUploadedWeights(): void
    {
        $static = str_repeat('a', 64) . '.woff2';
        $variable = str_repeat('c', 64) . '.var.ttf';
        $url = static fn (string $file): string => '/fonts/' . $file;

        $face = static fn (string $file, string $format, string $weight): string => '@font-face{font-family:"HAWKI Brand";src:url("/fonts/' . $file . '") format("' . $format . '");font-weight:' . $weight . ';font-display:swap}';
        $bold = str_repeat('b', 64) . '.otf';

        static::assertSame('', BrandTheme::fontFaces([], $url));
        static::assertSame('', BrandTheme::fontFaces([400 => 'x");}body{display:none'], $url));
        // Without a regular font no other weight is used: the whole interface would turn bold.
        static::assertSame('', BrandTheme::fontFaces([600 => $bold], $url));
        static::assertSame($face($static, 'woff2', '400'), BrandTheme::fontFaces([400 => $static], $url));
        static::assertSame($face($static, 'woff2', '400') . $face($bold, 'opentype', '600'), BrandTheme::fontFaces([400 => $static, 500 => null, 600 => $bold], $url));
        // A variable regular font provides every weight; the other files are left out.
        static::assertSame($face($variable, 'truetype', '100 900'), BrandTheme::fontFaces([400 => $variable, 600 => $bold], $url));

        // Italic cuts are declared like the upright ones, each style on its own, and only with a regular font.
        $italic = str_repeat('e', 64) . '.woff2';
        $variableItalic = str_repeat('f', 64) . '.var.woff2';
        $italicFace = static fn (string $file, string $weight): string => '@font-face{font-family:"HAWKI Brand";src:url("/fonts/' . $file . '") format("woff2");font-weight:' . $weight . ';font-style:italic;font-display:swap}';
        static::assertSame('', BrandTheme::fontFaces([], $url, [400 => $italic]));
        static::assertSame($face($variable, 'truetype', '100 900') . $italicFace($italic, '400'), BrandTheme::fontFaces([400 => $variable], $url, [400 => $italic]));
        static::assertSame($face($static, 'woff2', '400') . $italicFace($variableItalic, '100 900'), BrandTheme::fontFaces([400 => $static], $url, [400 => $variableItalic, 600 => $italic]));
    }

    public function testAnUploadedFontReplacesTheBaseFontFamily(): void
    {
        $regular = str_repeat('a', 64) . '.woff2';
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => null,
            'hawki.branding.status_colors' => BrandTheme::STATUS_COLORS, 'hawki.branding.font' => $regular]);

        $html = (new BrandTheme())->render();

        static::assertStringContainsString('src:url("/api/hawki/v1/brand-fonts/' . $regular . '")', $html);
        static::assertStringEndsWith(':root{--font-family-base:"HAWKI Brand",system-ui,sans-serif}</style>', $html);
    }

    public function testInvalidDeploymentValuesNeverReachTheStylesheet(): void
    {
        config(['hawki.branding.accent_color' => 'red}</style><script>', 'hawki.branding.corner_style' => '0}body{display:none', 'hawki.branding.logo_height' => '28}x']);

        static::assertNull(BrandTheme::accentColor());
        static::assertSame('', (new BrandTheme())->render());
    }
}
