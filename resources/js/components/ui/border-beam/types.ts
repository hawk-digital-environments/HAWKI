import type {AppTheme} from '$plugins/core/stores/ThemeStore.svelte.js';

/**
 * Shared types for the `BorderBeam` component and its CSS generator
 * (`styles.ts`). WHY: the CSS is generated per-instance at runtime (see
 * `generateBeamCSS` in `./styles.ts`), so `BorderBeam.svelte`, `styles.ts`,
 * and the preset tables all need to agree on the same size/theme vocabulary —
 * these types are the single source of truth for that vocabulary.
 */

/**
 * Size/type preset for the border beam effect
 *
 * Rotate family (traveling/spinning beam):
 * - 'sm': Small button-sized with compact glow
 * - 'md': Medium card-sized with full border glow
 * - 'line': Bottom-only traveling glow with breathe and spike animations
 */
export type BorderBeamSize = 'sm' | 'md' | 'line';

/**
 * Theme mode for adapting beam colors to background. `'auto'` is
 * `BorderBeam`-only — it resolves to the live app theme (`AppTheme`) via the
 * theme store; the CSS generator only ever receives the resolved `AppTheme`.
 */
export type BorderBeamTheme = AppTheme | 'auto';

/**
 * The two color stops a beam is painted from. Every blob in the palette is a mix of these
 * two, positioned by where it sits along the beam's travel, so the traveling highlight
 * sweeps from `from` through to `to` and back.
 *
 * Any CSS color works — hex, `rgb()`, `var(--token)` — because the values are only ever
 * spliced into generated `color-mix()` expressions, never parsed.
 */
export interface BeamColors {
    /** The stop at the start of the beam's travel. */
    from: string;
    /** The stop at the end of it. */
    to: string;
}

/**
 * Geometry preset for a `BorderBeamSize`. Consumed by `sizePresets` in
 * `./styles.ts` as the default border radius/width, and (for `'sm'`) the
 * reference dimensions the compact glow was authored for.
 */
export interface SizeConfig {
    borderRadius: number;
    borderWidth: number;
    width?: number;
    height?: number;
}

/**
 * Per-theme (`dark`/`light`) opacity/color tuning for a `BorderBeamSize`.
 * Consumed by `sizeThemePresets` in `./styles.ts`; `BorderBeam.svelte` reads
 * the entry matching the resolved theme and passes its fields into
 * `generateBeamCSS`.
 */
export interface ThemeColors {
    strokeOpacity: number;
    innerOpacity: number;
    bloomOpacity: number;
    innerShadow: string;
    saturation: number;
    /** Optional per-type default brightness. Falls back to 1.3. */
    brightness?: number;
    /** Optional opacity of the 1px hairline border that frames the element. Falls back to 0 (no hairline). */
    hairlineOpacity?: number;
}
