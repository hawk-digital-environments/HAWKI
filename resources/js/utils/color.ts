/**
 * Cover-contrast helpers: is a flat CSS color dark or light, so overlays
 * (breadcrumb chips over an assistant's full-bleed cover) can pick a shade
 * that reads on it. The browser-free half of astryx's `useImageMode` —
 * covers here are solid colors held as data, so no pixel sampling is needed.
 */

/**
 * APCA perceptual lightness from sRGB channels (0-255), 0 (black) to 1
 * (white). Handles saturated colors better than BT.709 gamma-luma or WCAG 2
 * relative luminance, which overestimate the brightness of reds and blues.
 * (After Meta's astryx, useImageMode.ts, MIT.)
 */
export function perceptualLightness(r: number, g: number, b: number): number {
    const lin = (c: number) => Math.pow(c / 255, 2.4);
    const y = 0.2126729 * lin(r) + 0.7151522 * lin(g) + 0.072175 * lin(b);
    return Math.pow(y, 0.56);
}

export type OverlayContrastMode = 'dark' | 'light';

/** sRGB channels of `rgb()`/`rgba()`/`hsl()`/`hsla()` strings, or null. */
function parseChannels(cssColor: string): [number, number, number] | null {
    const rgb = cssColor.match(/^rgba?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (rgb) {
        return [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])];
    }

    const hsl = cssColor.match(/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%?[\s,]+([\d.]+)%?/);
    if (hsl) {
        return hslToRgb(Number(hsl[1]), Number(hsl[2]), Number(hsl[3]));
    }

    return null;
}

function hslToRgb(h: number, s: number, l: number): [number, number, number] {
    const sat = s / 100;
    const light = l / 100;
    const k = (n: number) => (n + h / 30) % 12;
    const a = sat * Math.min(light, 1 - light);
    const f = (n: number) => light - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return [Math.round(f(0) * 255), Math.round(f(8) * 255), Math.round(f(4) * 255)];
}

/** Hidden probe resolving any color format the browser knows to rgb(). */
let probe: HTMLElement | null = null;

function resolveViaDom(cssColor: string): [number, number, number] | null {
    if (typeof document === 'undefined') return null;
    probe ??= document.documentElement.appendChild(document.createElement('span'));
    probe.style.color = '';
    probe.style.color = cssColor;
    return parseChannels(getComputedStyle(probe).color);
}

/**
 * Whether a flat CSS color reads as dark or light — the shade an overlay on
 * top of it should contrast with ('dark' cover → light overlay chips, and
 * vice versa). Unparseable input yields `fallback` (the default keeps the
 * caller's own styling).
 */
export function overlayContrastMode(
    cssColor: string | null | undefined,
    { threshold = 0.5, fallback = null }: { threshold?: number; fallback?: OverlayContrastMode | null } = {}
): OverlayContrastMode | null {
    if (!cssColor) return fallback;

    const channels = parseChannels(cssColor) ?? resolveViaDom(cssColor);
    if (!channels) return fallback;

    return perceptualLightness(...channels) > threshold ? 'light' : 'dark';
}

/**
 * One declaration's value out of an inline-CSS string like the avatar's
 * iconCss (`background: …; color: …`), or null. The declaration spellings
 * are the backend's IconCss whitelist, so a plain regex is enough.
 */
export function cssDeclaration(css: string, property: string): string | null {
    return css.match(new RegExp(`(?:^|;)\\s*${property}:\\s*([^;]+)`))?.[1]?.trim() ?? null;
}
