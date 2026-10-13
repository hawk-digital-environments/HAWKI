<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\View;

use App\Services\Frontend\View\BrandTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandTheme::class)]
class BrandThemeTest extends TestCase
{
    public function testItRendersNothingForTheDefaultTheme(): void
    {
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => 28]);

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
        static::assertStringContainsString('--corner-control:0', $html);
        static::assertStringNotContainsString('@layer', $html);
    }

    public function testItRendersTheLogoHeightInRemAndIgnoresValuesOutOfRange(): void
    {
        config(['hawki.branding.accent_color' => null, 'hawki.branding.corner_style' => 'round', 'hawki.branding.logo_height' => 36]);
        static::assertSame('<style>:root{--logo-height:2.25rem}</style>', (new BrandTheme())->render());

        config(['hawki.branding.logo_height' => 400]);
        static::assertSame('', (new BrandTheme())->render());
    }

    public function testInvalidDeploymentValuesNeverReachTheStylesheet(): void
    {
        config(['hawki.branding.accent_color' => 'red}</style><script>', 'hawki.branding.corner_style' => '0}body{display:none', 'hawki.branding.logo_height' => '28}x']);

        static::assertNull(BrandTheme::accentColor());
        static::assertSame('', (new BrandTheme())->render());
    }
}
