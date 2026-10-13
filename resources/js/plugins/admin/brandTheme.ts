/**
 * Live preview of the branding settings: applies a brand color and corner
 * style to the design tokens of the running page, the way the server does for
 * the first paint. Mirrors `app/Services/Frontend/View/BrandTheme.php` — keep
 * the two in step (the shared expectations are pinned in both test suites).
 */

/** Chroma of the default accent (`--color-accent-500`); a brand color this saturated uses the full ramp. */
const REFERENCE_CHROMA = 0.24;
const DEFAULT_ACCENT = { hue: 265, chroma: 1 };
/** `round` repeats `resources/css/tokens/radius.css`, so a preview can return to it over a server-rendered override. */
const CORNERS: Record<string, Record<string, string>> = {
    round: { xs: '8px', sm: '12px', md: '16px', lg: '24px', control: '9999px' },
    soft: { xs: '4px', sm: '6px', md: '8px', lg: '12px', control: '8px' },
    square: { xs: '0', sm: '0', md: '0', lg: '0', control: '0' }
};

export const isBrandColor = (value: unknown): value is string =>
    typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);

/** Converts a `#rrggbb` color to its OKLCH hue (degrees) and its chroma relative to the default accent. */
export function accentTokens(hex: string): { hue: number; chroma: number } {
    const [r, g, b] = [1, 3, 5].map((start) => {
        const value = parseInt(hex.slice(start, start + 2), 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    const a = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
    const bb = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;
    return {
        hue: Math.round((((Math.atan2(bb, a) * 180) / Math.PI + 360) % 360) * 10) / 10,
        chroma: Math.round(Math.min(1, Math.hypot(a, bb) / REFERENCE_CHROMA) * 1000) / 1000
    };
}

/**
 * Sets the brand tokens on `root`. An empty color means the HAWKI default; a
 * half-typed one leaves the current accent alone, so the page does not flash
 * back to the default between keystrokes.
 */
export function applyBrandTheme(
    theme: { accentColor?: unknown; cornerStyle?: unknown; logoHeight?: unknown },
    root: Pick<HTMLElement, 'style'> = document.documentElement
): void {
    const accent =
        isBrandColor(theme.accentColor) ? accentTokens(theme.accentColor)
        : !theme.accentColor ? DEFAULT_ACCENT
        : null;
    if (accent) {
        root.style.setProperty('--accent-hue', String(accent.hue));
        root.style.setProperty('--accent-chroma', String(accent.chroma));
    }
    for (const [size, radius] of Object.entries(CORNERS[String(theme.cornerStyle)] ?? CORNERS.round)) {
        root.style.setProperty(`--corner-${size}`, radius);
    }
    const height = Number(theme.logoHeight);
    if (theme.logoHeight == null) {
        // `initial` makes `var(--logo-height, …)` take its fallback even over a server-rendered value.
        root.style.setProperty('--logo-height', 'initial');
    } else if (Number.isInteger(height) && height >= 12 && height <= 36) {
        root.style.setProperty('--logo-height', `${height / 16}rem`);
    }
}
