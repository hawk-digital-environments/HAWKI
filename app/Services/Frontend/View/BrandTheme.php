<?php

namespace App\Services\Frontend\View;

use App\Services\Admin\BrandFonts;
use Illuminate\View\Component;

/**
 * Blade component `<x-brand-theme />` that applies the deployment's branding to the design tokens.
 *
 * The accent ramp in `resources/css/tokens/colors.css` is built from `--accent-hue` and
 * `--accent-chroma`, so one brand color re-themes every accent surface while the ramp keeps its
 * lightness steps and with them its contrast. The rule is unlayered on purpose: unlayered styles
 * win over the `tokens` layer regardless of source order.
 */
class BrandTheme extends Component
{
    /** Chroma of the default accent (`--color-accent-500`); a brand color this saturated uses the full ramp. */
    private const REFERENCE_CHROMA = 0.24;

    /** The built-in status colors; `colors.css` holds the same values. */
    public const STATUS_COLORS = [
        'success' => 'oklch(50% 0.17 145)',
        'warning' => 'oklch(62% 0.17 75)',
        'error' => 'oklch(52% 0.21 25)',
        'info' => 'oklch(55% 0.14 230)',
    ];

    private const FONT_FAMILY = 'HAWKI Brand';

    private const CORNERS = [
        'soft' => ['xs' => '4px', 'sm' => '6px', 'md' => '8px', 'lg' => '12px', 'control' => '8px', 'control-sm' => '5px'],
        'square' => ['xs' => '0', 'sm' => '0', 'md' => '0', 'lg' => '0', 'control' => '0px', 'control-sm' => '2px'],
    ];

    /** The configured brand color as `#rrggbb`, or null when none (or an invalid one) is configured. */
    public static function accentColor(): ?string
    {
        $color = config('hawki.branding.accent_color');

        return is_string($color) && 1 === preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
    }

    /**
     * Returns a CSS OKLCH color in its canonical spelling, `oklch(L% C H)`, or null when the value is
     * not one: lightness 0–100 %, chroma 0–0.4, hue 0–360, plain numbers only.
     */
    public static function oklch(mixed $value): ?string
    {
        if (!\is_string($value) || 1 !== preg_match('/^oklch\(\s*(\d{1,3}(?:\.\d{1,2})?)%\s+(\d(?:\.\d{1,3})?)\s+(\d{1,3}(?:\.\d{1,2})?)\s*\)$/i', trim($value), $match)) {
            return null;
        }

        [, $lightness, $chroma, $hue] = array_map('floatval', $match);

        return $lightness <= 100 && $chroma <= 0.4 && $hue <= 360 ? \sprintf('oklch(%s%% %s %s)', $lightness, $chroma, $hue) : null;
    }

    /**
     * Converts a `#rrggbb` color to the OKLCH hue (degrees) and the chroma relative to the default accent.
     *
     * @return array{hue: float, chroma: float}
     */
    public static function accentTokens(string $hex): array
    {
        [$hue, $chroma] = self::hueAndChroma($hex);

        return ['hue' => $hue, 'chroma' => round(min(1, $chroma / self::REFERENCE_CHROMA), 3)];
    }

    /**
     * @return array{float, float} OKLCH hue in degrees and chroma of a `#rrggbb` color
     */
    private static function hueAndChroma(string $hex): array
    {
        [$r, $g, $b] = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );
        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $b = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [round(fmod(rad2deg(atan2($b, $a)) + 360, 360), 1), hypot($a, $b)];
    }

    /**
     * The `@font-face` rule for the uploaded font, or an empty string without one.
     *
     * A variable font is declared for every weight. Any other font is declared as regular, so the
     * browser thickens it where bold is asked for.
     *
     * @param callable(string): string $url maps a stored file to its public URL
     */
    public static function fontFace(mixed $font, callable $url): string
    {
        if (!BrandFonts::isFile($font)) {
            return '';
        }

        return '@font-face{font-family:"' . self::FONT_FAMILY . '";src:url("' . $url($font) . '") format("' . BrandFonts::cssFormat($font)
            . '");font-weight:' . (BrandFonts::isVariableFile($font) ? '100 900' : '400') . ';font-display:swap}';
    }

    /**
     * Renders the token overrides, or nothing when the deployment uses the default theme.
     * Only validated values reach the stylesheet, also for values that come straight from `.env`.
     */
    public function render(): string
    {
        $tokens = [];

        if (null !== $color = self::accentColor()) {
            $accent = self::accentTokens($color);
            $tokens[] = '--accent-hue:' . $accent['hue'];
            $tokens[] = '--accent-chroma:' . $accent['chroma'];
        }

        foreach (self::STATUS_COLORS as $status => $default) {
            $color = self::oklch(config('hawki.branding.status_colors.' . $status));

            if (null !== $color && $default !== $color) {
                $tokens[] = '--color-' . $status . ':' . $color;
            }
        }

        foreach (self::CORNERS[config('hawki.branding.corner_style')] ?? [] as $size => $radius) {
            $tokens[] = '--corner-' . $size . ':' . $radius;
        }

        $height = config('hawki.branding.logo_height');

        // Unset leaves the logo at its own size: `HawkLogo.svelte` has a fallback per kind of logo.
        if (\is_int($height) && $height >= 12 && $height <= 36) {
            $tokens[] = '--logo-height:' . $height / 16 . 'rem';
        }

        $fonts = self::fontFace(
            config('hawki.branding.font'),
            static fn (string $file): string => route('api.brand-fonts.show', ['file' => $file], false),
        );

        if ('' !== $fonts) {
            $tokens[] = '--font-family-base:"' . self::FONT_FAMILY . '",system-ui,sans-serif';
        }

        return [] === $tokens ? '' : '<style>' . $fonts . ':root{' . implode(';', $tokens) . '}</style>';
    }
}
