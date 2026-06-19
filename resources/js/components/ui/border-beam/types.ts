import type {AppTheme} from '$lib/stores/ThemeStore.svelte.js';

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
 * Theme mode for adapting beam colors to background
 */
export type BorderBeamTheme = AppTheme | 'auto';

/**
 * Configuration for a size preset
 */
export interface SizeConfig {
    borderRadius: number;
    borderWidth: number;
    width?: number;
    height?: number;
}

/**
 * Theme color configuration
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
