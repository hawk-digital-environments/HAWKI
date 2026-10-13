/**
 * Live preview of the branding settings: applies a brand color and corner
 * style to the design tokens of the running page, the way the server does for
 * the first paint. Mirrors `app/Services/Frontend/View/BrandTheme.php` — keep
 * the two in step (the shared expectations are pinned in both test suites).
 */

/** Chroma of the default accent (`--color-accent-500`); a brand color this saturated uses the full ramp. */
const REFERENCE_CHROMA = 0.24;
const DEFAULT_ACCENT = { hue: 265, chroma: 1 };
/** The built-in status colors; an unset status returns to these (`colors.css` holds the same values). */
export const STATUS_COLORS: Record<string, string> = {
    success: 'oklch(50% 0.17 145)',
    warning: 'oklch(62% 0.17 75)',
    error: 'oklch(52% 0.21 25)',
    info: 'oklch(55% 0.14 230)'
};

/**
 * A CSS OKLCH color in its canonical spelling, `oklch(L% C H)`, or null when the value is not one:
 * lightness 0–100 %, chroma 0–0.4, hue 0–360, plain numbers only. Mirrors `BrandTheme::oklch()`.
 */
export function oklch(value: unknown): string | null {
    if (typeof value !== 'string') return null;
    const match = /^oklch\(\s*(\d{1,3}(?:\.\d{1,2})?)%\s+(\d(?:\.\d{1,3})?)\s+(\d{1,3}(?:\.\d{1,2})?)\s*\)$/i.exec(value.trim());
    if (!match) return null;
    const [lightness, chroma, hue] = match.slice(1).map(Number);
    return lightness <= 100 && chroma <= 0.4 && hue <= 360 ? `oklch(${lightness}% ${chroma} ${hue})` : null;
}
/** `round` repeats `resources/css/tokens/radius.css`, so a preview can return to it over a server-rendered override. */
const CORNERS: Record<string, Record<string, string>> = {
    round: { xs: '8px', sm: '12px', md: '16px', lg: '24px', control: '9999px' },
    soft: { xs: '4px', sm: '6px', md: '8px', lg: '12px', control: '8px' },
    square: { xs: '0', sm: '0', md: '0', lg: '0', control: '0' }
};

export const isBrandColor = (value: unknown): value is string =>
    typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);

/** OKLCH hue in degrees and chroma of a `#rrggbb` color. */
function hueAndChroma(hex: string): [number, number] {
    const [r, g, b] = [1, 3, 5].map((start) => {
        const value = parseInt(hex.slice(start, start + 2), 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    const a = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
    const bb = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;
    return [Math.round((((Math.atan2(bb, a) * 180) / Math.PI + 360) % 360) * 10) / 10, Math.hypot(a, bb)];
}
const round3 = (value: number) => Math.round(value * 1000) / 1000;

/** Converts a `#rrggbb` color to its OKLCH hue (degrees) and its chroma relative to the default accent. */
export function accentTokens(hex: string): { hue: number; chroma: number } {
    const [hue, chroma] = hueAndChroma(hex);
    return { hue, chroma: round3(Math.min(1, chroma / REFERENCE_CHROMA)) };
}

/**
 * Sets the brand tokens on `root`. An empty color means the HAWKI default; a
 * half-typed one leaves the current accent alone, so the page does not flash
 * back to the default between keystrokes.
 */
export function applyBrandTheme(
    theme: {
        accentColor?: unknown;
        cornerStyle?: unknown;
        logoHeight?: unknown;
        /** CSS OKLCH color per status, e.g. `{ error: 'oklch(52% 0.21 25)' }`. */
        statusColors?: Record<string, unknown>;
    },
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
    for (const [status, builtIn] of Object.entries(STATUS_COLORS)) {
        const value = theme.statusColors?.[status];
        // Empty means the built-in color; a half-typed value leaves the current one alone.
        const color = value ? oklch(value) : builtIn;
        if (color) root.style.setProperty(`--color-${status}`, color);
    }
    const height = Number(theme.logoHeight);
    if (theme.logoHeight == null) {
        // `initial` makes `var(--logo-height, …)` take its fallback even over a server-rendered value.
        root.style.setProperty('--logo-height', 'initial');
    } else if (Number.isInteger(height) && height >= 12 && height <= 36) {
        root.style.setProperty('--logo-height', `${height / 16}rem`);
    }
}

/** The accepted font formats by file extension, with the name browsers expect in `format()`. */
export const FONT_FORMATS: Record<string, string> = { woff2: 'woff2', woff: 'woff', ttf: 'truetype', otf: 'opentype' };
/** Upload limit in bytes; `BrandFonts::MAX_KILOBYTES` enforces the same on the server. */
export const FONT_MAX_BYTES = 5 * 1024 * 1024;

/** A stored font file: content hash, `.var` for a variable font, the format as extension (see `BrandFonts.php`). */
export const isFontFile = (value: unknown): value is string =>
    typeof value === 'string' && /^[a-f0-9]{64}(?:\.var)?\.(?:woff2|woff|ttf|otf)$/.test(value);
export const isVariableFontFile = (value: unknown): boolean => isFontFile(value) && value.includes('.var.');

const PREVIEW_FONT = 'HAWKI Brand Preview';
/** The stack of `--font-family-base` in `typography.css`, to return to when the font is removed. */
const DEFAULT_FONT_STACK = "'Fire Sans', system-ui, sans-serif";

/**
 * The `@font-face` rule for the uploaded font, or an empty string without one. Mirrors
 * `BrandTheme::fontFace()`: a variable font is declared for every weight, any other as regular.
 */
export function fontFace(font: unknown, url: (file: string) => string, family = PREVIEW_FONT): string {
    if (!isFontFile(font)) return '';
    const format = FONT_FORMATS[font.split('.').pop()!];
    const weight = isVariableFontFile(font) ? '100 900' : '400';
    return `@font-face{font-family:"${family}";src:url("${url(font)}") format("${format}");font-weight:${weight};font-display:swap}`;
}

/** Previews an uploaded font on the running page; without one it returns to the built-in stack. */
export function applyBrandFont(
    font: unknown,
    url: (file: string) => string,
    doc: Document = document
): void {
    const id = 'brand-font-preview';
    const css = fontFace(font, url);
    let style = doc.getElementById(id);
    if (!style) {
        style = doc.createElement('style');
        style.id = id;
        doc.head.append(style);
    }
    style.textContent = css;
    doc.documentElement.style.setProperty(
        '--font-family-base',
        css ? `"${PREVIEW_FONT}", system-ui, sans-serif` : DEFAULT_FONT_STACK
    );
}
