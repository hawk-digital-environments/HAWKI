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
        static::assertStringContainsString('--corner-control:0px;--corner-control-sm:2px', $html);
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

    public function testTheFontFaceDeclaresEveryWeightOnlyForAVariableFont(): void
    {
        $static = str_repeat('a', 64) . '.woff2';
        $variable = str_repeat('c', 64) . '.var.ttf';
        $url = static fn (string $file): string => '/fonts/' . $file;

        static::assertSame('', BrandTheme::fontFace(null, $url));
        static::assertSame('', BrandTheme::fontFace('x");}body{display:none', $url));
        static::assertSame('@font-face{font-family:"HAWKI Brand";src:url("/fonts/' . $static . '") format("woff2");font-weight:400;font-display:swap}', BrandTheme::fontFace($static, $url));
        static::assertSame('@font-face{font-family:"HAWKI Brand";src:url("/fonts/' . $variable . '") format("truetype");font-weight:100 900;font-display:swap}', BrandTheme::fontFace($variable, $url));
        static::assertStringContainsString('.otf") format("opentype")', BrandTheme::fontFace(str_repeat('d', 64) . '.otf', $url));
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
