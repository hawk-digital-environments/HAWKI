import type { SizeConfig, ThemeColors, BorderBeamSize, BeamColors } from './types';

/**
 * CSS generator for the `BorderBeam` animated border effect.
 *
 * WHAT: builds a complete, self-contained CSS stylesheet (as a string) for one
 * `BorderBeam` instance — `@property` registrations, keyframes, and
 * `[data-beam="<id>"]` rules for the `::before`/`::after`/bloom layers.
 *
 * WHY this exists as generated CSS rather than static `<style>` rules: the
 * beam's colors, gradient positions, and durations are per-instance and
 * driven by JS-computed values (size/theme/brightness/hue-range/measured
 * element dimensions), and several gradients must be dynamically sized to the
 * wrapped element (`--beam-fit-w/-h`, `--beam-fill`). CSS custom properties
 * alone can't express the conditional mask/gradient composition needed per
 * size variant, so each instance gets its own scoped stylesheet keyed by a
 * unique id (see `BorderBeam.svelte`, which injects the result via `{@html}`).
 *
 * HOW TO USE: call `generateBeamCSS()` with a fully-resolved options object
 * (see `GenerateStylesOptions`) and inject the returned string into a
 * `<style>` tag. `sizePresets` and `sizeThemePresets` supply the default
 * geometry/opacity values that `BorderBeam.svelte` merges with prop overrides
 * before calling `generateBeamCSS`. Everything else in this file (blob
 * tables, gradient builders, `generateBorderlikeCSS`/`generateLineVariantCSS`)
 * is an internal implementation detail — not exported, except for
 * `getBeamAccentColors`, which shares the beam's stroke palette with adjacent UI.
 *
 * The blob tables are authored in a single brand blue, which every gradient
 * builder remaps onto a caller-supplied pair of stops when the `colors` option
 * is set — see `stopColor`.
 */

// ── CSS template helpers ─────────────────────────────────────────────────────
// Emit a paired `-webkit-mask`/`mask` block. The webkit and standard composite
// values are NOT derivable from one another — they differ per call site — so
// they are passed explicitly and preserved verbatim.
function dualMask(image: string, webkitComposite: string, stdComposite: string): string {
  return `-webkit-mask: ${image};\n  -webkit-mask-composite: ${webkitComposite};\n  mask: ${image};\n  mask-composite: ${stdComposite};`;
}

// Emit an `@property` registration for a per-instance custom property.
function propReg(name: string, syntax: string, initial: string): string {
  return `@property --${name} {\n  syntax: "${syntax}";\n  initial-value: ${initial};\n  inherits: true;\n}`;
}

// Emit the paired fade-in/fade-out keyframes that drive `--beam-opacity-<id>`.
function fadeKeyframes(id: string): string {
  return `@keyframes beam-fade-in-${id} { to { --beam-opacity-${id}: 1; } }\n@keyframes beam-fade-out-${id} { from { --beam-opacity-${id}: 1; } to { --beam-opacity-${id}: 0; } }`;
}

/**
 * Size presets for border radius and dimensions, keyed by `BorderBeamSize`.
 * `BorderBeam.svelte` uses these as the default border radius/width when no
 * explicit `borderRadius` prop (and no auto-detected child radius) is given.
 */
export const sizePresets: Record<BorderBeamSize, SizeConfig> = {
  sm: {
    borderRadius: 32,
    borderWidth: 1,
    width: 70,
    height: 36,
  },
  md: {
    borderRadius: 16,
    borderWidth: 1,
  },
  line: {
    borderRadius: 16,
    borderWidth: 1,
  },
};

/**
 * Per-size, per-theme (`dark`/`light`) opacity/saturation/brightness presets
 * matching the tuned v5 control panel defaults. `BorderBeam.svelte` looks up
 * `sizeThemePresets[size][resolvedTheme]` and lets individual props
 * (`saturation`, `brightness`, …) override specific fields.
 */
export const sizeThemePresets: Record<BorderBeamSize, Record<'dark' | 'light', ThemeColors>> = {
  sm: {
    dark: {
      strokeOpacity: 0.46,
      innerOpacity: 0.24,
      bloomOpacity: 0.38,
      innerShadow: 'rgba(255, 255, 255, 0.3)',
      saturation: 1.2,
    },
    light: {
      strokeOpacity: 0.12,
      innerOpacity: 0.3,
      bloomOpacity: 0.16,
      innerShadow: 'rgba(255, 255, 255, 0.3)',
      saturation: 1.8,
    },
  },
  md: {
    dark: {
      // Matched to the `line` preset so the full-border glow reads as intensely
      // as the bottom traveling beam.
      strokeOpacity: 1.4,
      innerOpacity: 0.92,
      bloomOpacity: 1.0,
      innerShadow: 'rgba(255, 255, 255, 0.27)',
      saturation: 1.2,
    },
    light: {
      strokeOpacity: 0.36,
      innerOpacity: 0.5,
      bloomOpacity: 0.5,
      innerShadow: 'rgba(255, 255, 255, 0.27)',
      saturation: 1.5,
    },
  },
  line: {
    dark: {
      strokeOpacity: 1.4,
      innerOpacity: 0.92,
      bloomOpacity: 1.0,
      innerShadow: 'rgba(255, 255, 255, 0.1)',
      saturation: 1.2,
    },
    light: {
      strokeOpacity: 0.36,
      innerOpacity: 0.5,
      bloomOpacity: 0.5,
      innerShadow: 'rgba(255, 255, 255, 0.1)',
      saturation: 1.95,
    },
  },
};

/**
 * Color palette (HAWKI brand) — pure blues/indigos anchored on #2F2ABF
 * (rgb 47, 42, 191).
 *
 * Border blobs as positional tuples: [color, pos, size].
 */
const BORDER_BLOBS: ReadonlyArray<readonly [string, string, string]> = [
  ['rgb(47, 42, 191)', '33% -7.4%', '70px 40px'],
  ['rgb(45, 80, 240)', '12% -5%', '60px 35px'],
  ['rgb(40, 60, 220)', '2.1% 68.3%', '40px 70px'],
  ['rgb(40, 90, 245)', '2.1% 68.3%', '20px 35px'],
  ['rgb(30, 55, 215)', '74.4% 100%', '180px 32px'],
  ['rgb(45, 80, 240)', '55% 100%', '85px 26px'],
  ['rgb(50, 90, 245)', '93.9% 0%', '74px 32px'],
  ['rgb(47, 42, 191)', '100% 27.1%', '26px 42px'],
  ['rgb(55, 95, 250)', '100% 27.1%', '52px 48px'],
];

const SPIKE_DARK = { primary: 'rgb(55, 95, 250)', secondary: 'rgba(40, 60, 220, 0.98)' };
const SPIKE_LIGHT = { primary: 'rgb(47, 42, 191)', secondary: 'rgb(40, 70, 210)' };

/**
 * Small size color palette (compact gradients for button-sized elements).
 * Border blobs as positional tuples: [color, pos, size].
 */
const SMALL_BLOBS_BORDER: ReadonlyArray<readonly [string, string, string]> = [
  ['rgb(40, 80, 220)', '2% 68%', '9px 18px'],
  ['rgb(40, 120, 255)', '2% 68%', '4px 8px'],
  ['rgb(30, 70, 215)', '72% -3%', '59px 9px'],
  ['rgb(50, 110, 255)', '74% 100%', '42px 7px'],
  ['rgb(60, 130, 255)', '100% 27%', '10px 17px'],
  ['rgb(47, 42, 191)', '100% 27%', '10px 18px'],
  ['rgb(80, 140, 255)', '100% 27%', '5px 10px'],
  ['rgb(40, 90, 230)', '100% 27%', '11px 12px'],
];

// Per-blob alpha for the small inner-tint layer, derived onto SMALL_BLOBS_BORDER colors.
const SMALL_INNER_ALPHA = [0.5, 0.45, 0.35, 0.35, 0.3, 0.4, 0.3, 0.3];

// Convert an `rgb(r, g, b)` color into `rgba(r, g, b, alpha)`. The alpha may be a
// number or a pre-formatted string (e.g. '0.50') to preserve exact decimal output.
function rgbToRgba(color: string, alpha: number | string): string {
  return color.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);
}

/**
 * Fade any color to `alpha`, whatever notation it arrived in.
 *
 * The authored palette is all `rgb()`, so it keeps going through `rgbToRgba` and its exact
 * decimal output. A caller's stops can be any CSS color — and once mixed they are
 * `color-mix()` expressions rather than parseable channels — so those fade through
 * `color-mix()` toward transparent instead.
 */
function fadeColor(color: string, alpha: number | string): string {
  if (color.startsWith('rgb(')) {
    return rgbToRgba(color, alpha);
  }
  return `color-mix(in oklab, ${color} ${(Number(alpha) * 100).toFixed(1)}%, transparent)`;
}

/**
 * Remap one authored blob color onto the caller's two stops, or leave it alone when no
 * stops were given.
 *
 * `t` is where the blob sits along the beam's travel — 0 lands on `from`, 1 on `to`. The
 * border variants derive it from the blob's angle around the perimeter and the `line`
 * variant from its offset along the line, so the pair reads as a gradient the traveling
 * highlight sweeps through rather than as two colors scattered at random.
 *
 * Mixed in `oklab` so a blend of two saturated stops stays saturated, instead of dipping
 * through grey at the midpoint the way sRGB interpolation does.
 */
function stopColor(colors: BeamColors | undefined, authored: string, t: number): string {
  if (!colors) {
    return authored;
  }
  return `color-mix(in oklab, ${colors.to} ${(t * 100).toFixed(1)}%, ${colors.from})`;
}

/**
 * Where a blob positioned at a `"<x>% <y>%"` background position sits around the border,
 * as a 0–1 fraction clockwise from twelve o'clock — the same direction and origin the
 * `conic-gradient` beam sweeps in, so the stops line up with its travel.
 */
function perimeterT(pos: string): number {
  const [x, y] = pos.split(' ').map(parseFloat);
  const angle = Math.atan2(x - 50, 50 - y);
  return ((angle / (2 * Math.PI)) + 1) % 1;
}

function getSmallColorGradients(colors?: BeamColors): string {
  return SMALL_BLOBS_BORDER
    .map(([color, pos, size]) =>
      `radial-gradient(ellipse ${size} at ${pos}, ${stopColor(colors, color, perimeterT(pos))}, transparent)`)
    .join(',\n    ');
}

function getSmallInnerGradients(colors?: BeamColors): string {
  return SMALL_BLOBS_BORDER
    .map(([color, pos, size], i) =>
      `radial-gradient(ellipse ${size} at ${pos}, ${fadeColor(stopColor(colors, color, perimeterT(pos)), SMALL_INNER_ALPHA[i])}, transparent)`)
    .join(',\n    ');
}

/**
 * Scale a fixed `"<w>px <h>px"` gradient size by the per-axis element-fit factors
 * (`--beam-fit-w`/`--beam-fit-h`, default 1). The color/inner tint blobs for the
 * `md` border are authored for a ~350x140 reference; without this they stay small
 * and corner-clustered on a large surface, so the traveling window reveals mostly
 * the white gradient with little color. `scale` lets callers shrink them slightly.
 *
 * `--beam-fill` (default 1) is an extra growth factor the component raises on
 * large surfaces: even at full fit the blobs sit at fixed perimeter positions
 * with big gaps between them, so the rotating window crosses bare stretches and
 * the color reads as detached patches. Over-sizing the blobs lets neighbours
 * overlap into a continuous ring, truer to the rotation.
 */
function fitScaledSize(size: string, scale = 1): string {
  const [w, h] = size.split(' ').map(s => parseInt(s) * scale);
  return `calc(${w}px * var(--beam-fit-w, 1) * var(--beam-fill, 1)) calc(${h}px * var(--beam-fit-h, 1) * var(--beam-fill, 1))`;
}

function getColorGradients(colors?: BeamColors): string {
  return BORDER_BLOBS
    .map(([color, pos, size]) =>
      `radial-gradient(ellipse ${fitScaledSize(size)} at ${pos}, ${stopColor(colors, color, perimeterT(pos))}, transparent)`)
    .join(',\n    ');
}

function getInnerGradients(colors?: BeamColors): string {
  const baseOpacity = 0.45;
  return BORDER_BLOBS
    .map(([color, pos, size]) => {
      const rgba = fadeColor(stopColor(colors, color, perimeterT(pos)), baseOpacity);
      const smallerSize = fitScaledSize(size, 0.9);
      return `radial-gradient(ellipse ${smallerSize} at ${pos}, ${rgba}, transparent)`;
    })
    .join(',\n    ');
}

/**
 * The beam's two brightest stroke colors. Caller-supplied stops *are* that pair — they are
 * the ends of the beam's travel — so they stand in for the authored spikes directly rather
 * than being mixed.
 */
function getSpikeColors(isDark: boolean, colors?: BeamColors) {
  if (colors) {
    return { primary: colors.from, secondary: colors.to };
  }
  return isDark ? SPIKE_DARK : SPIKE_LIGHT;
}

/**
 * The beam's two brightest stroke colors for a given theme — the same pair the traveling
 * highlight is built from. With no `colors` that is the authored `SPIKE_DARK`/`SPIKE_LIGHT`
 * brand blue; with a pair it is the pair itself, since the caller's stops are the ends of
 * the beam's travel.
 *
 * Exported so UI *next to* a beam can borrow its palette instead of re-deriving it:
 * `TextReveal` inside a beam-wrapped mention chip tints its characters with these, so the
 * text lights up in the same colors as the border around it.
 */
export function getBeamAccentColors(
  theme: 'dark' | 'light',
  colors?: BeamColors
): { primary: string; secondary: string } {
  return getSpikeColors(theme === 'dark', colors);
}

// Line color blobs as positional tuples: [color, sizeW, sizeH, offsetX, offsetY].
type LineBlob = readonly [string, number, number, number, number];

const LINE_BLOBS_DARK: ReadonlyArray<LineBlob> = [
  ['rgb(47, 42, 191)', 36, 36, 0, 2],
  ['rgb(50, 110, 255)', 30, 32, 39, 0],
  ['rgb(40, 80, 220)', 33, 28, -36, 2],
  ['rgb(30, 70, 215)', 29, 34, -54, 0],
  ['rgb(40, 120, 255)', 27, 30, 51, -1],
  ['rgb(60, 130, 255)', 36, 24, 21, 1],
  ['rgb(50, 100, 240)', 30, 22, -21, 0],
  ['rgb(80, 140, 255)', 25, 28, 66, 1],
  ['rgb(40, 90, 230)', 23, 30, -66, -1],
];

const LINE_BLOBS_LIGHT: ReadonlyArray<LineBlob> = [
  ['rgb(47, 42, 191)', 45, 36, 0, 2],
  ['rgb(40, 90, 220)', 35, 32, 65, 0],
  ['rgb(40, 70, 200)', 40, 28, -60, 2],
  ['rgb(30, 60, 210)', 35, 34, -90, 0],
  ['rgb(40, 100, 220)', 38, 30, 85, -1],
  ['rgb(30, 80, 230)', 50, 24, 35, 1],
  ['rgb(40, 95, 210)', 40, 22, -35, 0],
  ['rgb(45, 110, 225)', 35, 28, 110, 1],
  ['rgb(35, 55, 200)', 30, 30, -110, -1],
];

/**
 * Where a `line` blob sits along the beam, as a 0–1 fraction across the spread of offsets
 * in its palette — the left-to-right equivalent of `perimeterT`.
 */
function lineT(offsetX: number, offsets: readonly number[]): number {
  const min = Math.min(...offsets);
  const max = Math.max(...offsets);
  return max === min ? 0 : (offsetX - min) / (max - min);
}

function getLineColorGradients(isDark: boolean, id: string, colors?: BeamColors): string {
  const palette = isDark ? LINE_BLOBS_DARK : LINE_BLOBS_LIGHT;
  const offsets = palette.map(blob => blob[3]);
  return palette
    .map(([authored, sizeW, sizeH, offsetX, offsetY]) => {
      const color = stopColor(colors, authored, lineT(offsetX, offsets));
      const offsetXStr = offsetX === 0 ? '' : (offsetX > 0 ? ` + ${offsetX}px` : ` - ${Math.abs(offsetX)}px`);
      const offsetYStr = offsetY === 0 ? '' : (offsetY > 0 ? ` + ${offsetY}px` : ` - ${Math.abs(offsetY)}px`);
      return `radial-gradient(ellipse calc(${sizeW}px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(${sizeH}px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px${offsetXStr}) calc(100%${offsetYStr}), ${color}, transparent)`;
    })
    .join(',\n       ');
}

// Inner gradient geometry matching v5.css exactly. Colors are derived from
// LINE_BLOBS_DARK with the per-blob alphas in LINE_INNER_ALPHA.
const LINE_INNER_ALPHA = ['0.48', '0.42', '0.48', '0.42', '0.50', '0.45', '0.40', '0.45', '0.52'];
// [sizeW, sizeH, offsetX, offsetY]
const LINE_INNER_GEOM: ReadonlyArray<readonly [number, number, number, number]> = [
  [33, 30, 0, 0],
  [24, 26, 39, -3],
  [27, 24, -36, 0],
  [23, 28, -54, -2],
  [24, 24, 51, -1],
  [30, 20, 21, 0],
  [25, 18, -21, -2],
  [21, 24, 66, 0],
  [18, 26, -66, -1],
];

function getLineInnerGradients(id: string, colors?: BeamColors): string {
  const offsets = LINE_BLOBS_DARK.map(blob => blob[3]);
  return LINE_INNER_GEOM
    .map(([sizeW, sizeH, offsetX, offsetY], i) => {
      // Keyed to the matching color blob's position, so tint and glow share a stop.
      const stop = stopColor(colors, LINE_BLOBS_DARK[i][0], lineT(LINE_BLOBS_DARK[i][3], offsets));
      const color = fadeColor(stop, LINE_INNER_ALPHA[i]);
      const offsetXStr = offsetX === 0 ? '' : (offsetX > 0 ? ` + ${offsetX}px` : ` - ${Math.abs(offsetX)}px`);
      const offsetYStr = offsetY === 0 ? '' : ` - ${Math.abs(offsetY)}px`;
      return `radial-gradient(ellipse calc(${sizeW}px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(${sizeH}px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px${offsetXStr}) calc(100%${offsetYStr}), ${color}, transparent)`;
    })
    .join(',\n    ');
}

// Line bloom spike colors as 2-tuples: [color1, color2]. 5 spikes per theme.
type BloomSpike = readonly [string, string];

const LINE_BLOOM_DARK: ReadonlyArray<BloomSpike> = [
  ['rgb(40, 80, 220)', 'rgb(40, 80, 220)'],
  ['rgba(50, 110, 255, 0.59)', 'rgba(50, 110, 255, 0.29)'],
  ['rgb(47, 42, 191)', 'rgb(47, 42, 191)'],
  ['rgba(60, 130, 255, 0.91)', 'rgba(60, 130, 255, 0.45)'],
  ['rgb(40, 120, 255)', 'rgb(40, 120, 255)'],
];

const LINE_BLOOM_LIGHT: ReadonlyArray<BloomSpike> = [
  ['rgb(40, 60, 190)', 'rgba(40, 60, 190, 0.8)'],
  ['rgba(40, 90, 210, 0.7)', 'rgba(40, 90, 210, 0.46)'],
  ['rgb(47, 42, 191)', 'rgba(47, 42, 191, 0.82)'],
  ['rgb(30, 70, 200)', 'rgba(30, 70, 200, 0.7)'],
  ['rgb(40, 100, 210)', 'rgba(40, 100, 210, 0.78)'],
];

function withAlpha(color: string, alpha: number): string {
  const rgbaMatch = color.match(/^rgba\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*[\d.]+\s*\)$/);
  if (rgbaMatch) return `rgba(${rgbaMatch[1]}, ${rgbaMatch[2]}, ${rgbaMatch[3]}, ${alpha})`;
  const rgbMatch = color.match(/^rgb\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*\)$/);
  if (rgbMatch) return `rgba(${rgbMatch[1]}, ${rgbMatch[2]}, ${rgbMatch[3]}, ${alpha})`;
  // Not parseable as channels — a caller's stop, or a mix of two. Fade it as an expression.
  return fadeColor(color, alpha);
}

// The horizontal position of each bloom spike, matching the `at <n>%` in the gradients
// below — the two `sc*` spikes first, then the five `spikes[*]`. Used to place each one
// between the caller's stops.
const LINE_BLOOM_T = [0.08, 0.22, 0.36, 0.5, 0.64, 0.78, 0.92];

function getLineBloomGradients(isDark: boolean, id: string, colors?: BeamColors): string {
  const spikeColors = getSpikeColors(isDark, colors);
  const bloomData = (isDark ? LINE_BLOOM_DARK : LINE_BLOOM_LIGHT).map(
    ([first, second], i) =>
      [
        stopColor(colors, first, LINE_BLOOM_T[i + 2]),
        stopColor(colors, second, LINE_BLOOM_T[i + 2])
      ] as BloomSpike
  );

  const sc1     = spikeColors.primary;
  const sc1_mid = spikeColors.primary;
  const sc2     = spikeColors.secondary;
  const sc2_mid = withAlpha(spikeColors.secondary, 0.49);

  const spikes = bloomData;

  const thinW1 = '0.8px';
  const thinW2 = '2px';
  const thinW3 = '1.2px';
  const thinW4 = '0.6px';
  const thinH1 = '92px';
  const thinH2 = '72px';
  const thinH3 = '85px';
  const thinH4 = '60px';
  const thinLW = '1px';

  // Main glow: center dot + ambient
  const glowDotC   = 'rgba(255, 255, 255, 1)';
  const glowDot20  = 'rgba(255, 255, 255, 0.9)';
  const glowDot50  = 'rgba(255, 255, 255, 0.5)';
  const glowAmbC   = 'rgba(255, 255, 255, 0.3)';
  const glowAmb25  = 'rgba(255, 255, 255, 0.12)';
  const glowAmb55  = 'rgba(255, 255, 255, 0.03)';

  if (isDark) {
    return `radial-gradient(ellipse calc(${thinW1} * var(--beam-spike-${id})) calc(${thinH1} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 8% calc(100% - 2px), ${sc1}, ${sc1_mid} 30%, transparent 88%),
       radial-gradient(ellipse calc(10px * var(--beam-spike2-${id})) calc(35px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 22% calc(100% - 4px), ${sc2}, ${sc2_mid} 50%, transparent 95%),
       radial-gradient(ellipse calc(${thinW2} * (2 - var(--beam-spike-${id}))) calc(${thinH2} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 36% calc(100% - 3px), ${spikes[0][0]}, ${spikes[0][1]} 40%, transparent 90%),
       radial-gradient(ellipse calc(14px * var(--beam-spike2-${id})) calc(28px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 50% calc(100% - 2px), ${spikes[1][0]}, ${spikes[1][1]} 55%, transparent 96%),
       radial-gradient(ellipse calc(${thinW3} * (2 - var(--beam-spike2-${id}))) calc(${thinH3} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 64% calc(100% - 4px), ${spikes[2][0]}, ${spikes[2][1]} 35%, transparent 89%),
       radial-gradient(ellipse calc(7px * var(--beam-spike-${id})) calc(45px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 78% calc(100% - 2px), ${spikes[3][0]}, ${spikes[3][1]} 48%, transparent 94%),
       radial-gradient(ellipse calc(${thinW4} * (2 - var(--beam-spike-${id}))) calc(${thinH4} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 92% calc(100% - 3px), ${spikes[4][0]}, ${spikes[4][1]} 42%, transparent 91%),
       radial-gradient(ellipse calc(21px * var(--beam-spike-${id})) calc(15px * var(--beam-spike2-${id})) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) calc(100% + 1px), ${glowDotC} 0%, ${glowDot20} 20%, ${glowDot50} 50%, transparent 100%),
       radial-gradient(ellipse calc(42px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(40px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%, ${glowAmbC} 0%, ${glowAmb25} 25%, ${glowAmb55} 55%, transparent 80%)`;
  } else {
    const sc1_lt = withAlpha(spikeColors.primary, 0.85);
    const sc2_lt = withAlpha(spikeColors.secondary, 0.7);
    return `radial-gradient(ellipse calc(${thinW1} * var(--beam-spike-${id})) calc(${thinH1} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 8% calc(100% - 2px), ${sc1}, ${sc1_lt} 30%, transparent 88%),
       radial-gradient(ellipse calc(10px * var(--beam-spike2-${id})) calc(35px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 22% calc(100% - 4px), ${sc2}, ${sc2_lt} 50%, transparent 95%),
       radial-gradient(ellipse calc(${thinW2} * (2 - var(--beam-spike-${id}))) calc(${thinH2} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 36% calc(100% - 3px), ${spikes[0][0]}, ${spikes[0][1]} 40%, transparent 90%),
       radial-gradient(ellipse calc(14px * var(--beam-spike2-${id})) calc(28px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 50% calc(100% - 2px), ${spikes[1][0]}, ${spikes[1][1]} 55%, transparent 96%),
       radial-gradient(ellipse calc(${thinW3} * (2 - var(--beam-spike2-${id}))) calc(${thinH3} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 64% calc(100% - 4px), ${spikes[2][0]}, ${spikes[2][1]} 35%, transparent 89%),
       radial-gradient(ellipse calc(7px * var(--beam-spike-${id})) calc(45px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 78% calc(100% - 2px), ${spikes[3][0]}, ${spikes[3][1]} 48%, transparent 94%),
       radial-gradient(ellipse calc(${thinLW} * (2 - var(--beam-spike-${id}))) calc(${thinH4} * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at 92% calc(100% - 3px), ${spikes[4][0]}, ${spikes[4][1]} 42%, transparent 91%),
       radial-gradient(ellipse calc(50px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(32px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) calc(100%), ${glowDotC} 0%, ${glowDot20} 30%, ${glowDot50} 60%, transparent 85%)`;
  }
}

// Pauses every animation on an instance (wrapper + pseudo layers + bloom) when it
// carries the `data-paused` attribute. Driven by an IntersectionObserver so beams
// that are scrolled offscreen stop doing per-frame paint work entirely.
function pausedAnimationsRule(id: string): string {
  return `
[data-beam="${id}"][data-paused],
[data-beam="${id}"][data-paused]::after,
[data-beam="${id}"][data-paused]::before,
[data-beam="${id}"][data-paused] [data-beam-bloom] {
  animation-play-state: paused !important;
}`;
}

interface GenerateStylesOptions {
  id: string;
  borderRadius: number;
  borderWidth: number;
  duration: number;
  strokeOpacity: number;
  innerOpacity: number;
  bloomOpacity: number;
  innerShadow: string;
  size: BorderBeamSize;
  staticColors: boolean;
  brightness: number;
  saturation: number;
  hueRange: number;
  /**
   * The two stops to paint the beam from. Omit for the authored brand-blue palette;
   * supply a pair to color-code an instance — e.g. one pair per chat assistant.
   */
  colors?: BeamColors;
  theme: 'dark' | 'light';
  /** Opacity of the 1px hairline outline. Falls back to 0. */
  hairlineOpacity?: number;
  /**
   * 'line' only: drive the beam's horizontal position externally (via the
   * `--beam-x-<id>` custom property the consumer sets) instead of the looping
   * `beam-travel` keyframe. Used for a one-shot, progress-style sweep that tracks
   * an async task rather than looping. The end-fade is also dropped so the glow
   * stays fully lit across the whole pass.
   */
  manualProgress?: boolean;
}

/**
 * Generate complete CSS for a BorderBeam instance
 */
export function generateBeamCSS(options: GenerateStylesOptions): string {
  const { size } = options;

  if (size === 'line') {
    return generateLineVariantCSS(options);
  }
  
  if (size === 'sm') {
    return generateBorderlikeCSS(options, buildSmConfig(options));
  }

  return generateBorderlikeCSS(options, buildMdConfig(options));
}

// Shared body for the rotate-family border-like variants ('sm' and 'md'), which
// differ only in their gradient sources and the ::after / ::before mask details
// (plus the inner box-shadow blur radius and where the ::before clip-path sits).
interface BorderlikeConfig {
  colorGradients: string;
  innerGradients: string;
  // ::after traveling-stroke mask — complete multi-line declaration block,
  // indented to sit under the rule (no leading/trailing newline).
  afterMaskBlock: string;
  // ::before inner-glow mask — complete multi-line declaration block.
  beforeMaskBlock: string;
  // ::before inner box-shadow blur radius (px).
  innerBoxShadowBlur: number;
  // The ::before's `clip-path` line. 'sm' places it right after border-radius
  // (so it precedes background); 'md' places it after the opacity line.
  beforeClipPathEarly: boolean;
}

function generateBorderlikeCSS(options: GenerateStylesOptions, config: BorderlikeConfig): string {
  const {
    id,
    borderRadius,
    borderWidth,
    duration,
    strokeOpacity,
    innerOpacity,
    bloomOpacity,
    innerShadow,
    staticColors,
    brightness,
    saturation,
    hueRange,
  } = options;

  const {
    colorGradients,
    innerGradients,
    afterMaskBlock,
    beforeMaskBlock,
    innerBoxShadowBlur,
    beforeClipPathEarly,
  } = config;

  const innerRadius = Math.max(0, borderRadius - borderWidth);

  const finalStrokeOpacity = strokeOpacity;
  const finalInnerOpacity = innerOpacity;
  const finalBloomOpacity = bloomOpacity;

  const hueShiftAnimation = staticColors
    ? ''
    : `animation: beam-hue-shift-${id} 12s ease-in-out infinite;`;

  const hueShiftKeyframes = staticColors ? '' : `
@keyframes beam-hue-shift-${id} {
  0% { filter: hue-rotate(-${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  50% { filter: hue-rotate(${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  100% { filter: hue-rotate(-${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
}`;

  const isLight = options.theme === 'light';
  const whiteGradient = isLight
    ? `conic-gradient(
        from var(--beam-angle-${id}),
        transparent 0%, transparent 54%,
        rgba(255, 255, 255, 0.4) 57%,
        rgba(255, 255, 255, 0.7) 60%,
        rgba(255, 255, 255, 0.9) 63%,
        rgba(255, 255, 255, 1.0) 66%,
        rgba(255, 255, 255, 0.9) 69%,
        rgba(255, 255, 255, 0.7) 72%,
        rgba(255, 255, 255, 0.4) 75%,
        transparent 78%, transparent 100%
      )`
    : `conic-gradient(
        from var(--beam-angle-${id}),
        transparent 0%, transparent 54%,
        rgba(255, 255, 255, 0.1) 57%,
        rgba(255, 255, 255, 0.3) 60%,
        rgba(255, 255, 255, 0.6) 63%,
        rgba(255, 255, 255, 0.75) 66%,
        rgba(255, 255, 255, 0.6) 69%,
        rgba(255, 255, 255, 0.3) 72%,
        rgba(255, 255, 255, 0.1) 75%,
        transparent 78%, transparent 100%
      )`;

  const bloomGradient = `conic-gradient(
        from var(--beam-angle-${id}),
        transparent 0%, transparent 58%,
        rgba(255, 255, 255, 0.03) 62%,
        rgba(255, 255, 255, 0.08) 65%,
        rgba(255, 255, 255, 0.2) 67%,
        rgba(255, 255, 255, 0.45) 69%,
        rgba(255, 255, 255, 0.85) 70%,
        rgba(255, 255, 255, 0.85) 70.5%,
        rgba(255, 255, 255, 0.45) 71.5%,
        rgba(255, 255, 255, 0.2) 73%,
        rgba(255, 255, 255, 0.08) 75%,
        rgba(255, 255, 255, 0.03) 78%,
        transparent 82%
      )`;

  const beforeClipEarly = beforeClipPathEarly ? `\n  clip-path: inset(0 round ${borderRadius}px);` : '';
  const beforeClipLate = beforeClipPathEarly ? '' : `\n  clip-path: inset(0 round ${borderRadius}px);`;

  return `
${propReg(`beam-angle-${id}`, '<angle>', '0deg')}

${propReg(`beam-opacity-${id}`, '<number>', '0')}

[data-beam="${id}"] {
  position: relative;
  border-radius: ${borderRadius}px;
  overflow: hidden;
}

[data-beam="${id}"][data-active] {
  animation:
    beam-spin-${id} ${duration}s linear infinite,
    beam-fade-in-${id} 0.6s ease forwards;
}

[data-beam="${id}"][data-fading] {
  animation:
    beam-spin-${id} ${duration}s linear infinite,
    beam-fade-out-${id} 0.5s ease forwards;
}

[data-beam="${id}"][data-active]::after,
[data-beam="${id}"][data-fading]::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: ${innerRadius}px;
  padding: ${borderWidth}px;
  clip-path: inset(0 round ${borderRadius}px);
  background: ${whiteGradient},${colorGradients};
${afterMaskBlock}
  pointer-events: none;
  z-index: 2;
  opacity: calc(var(--beam-opacity-${id}) * ${finalStrokeOpacity.toFixed(2)} * var(--beam-strength, 1));
  ${hueShiftAnimation}
}

[data-beam="${id}"][data-active]::before,
[data-beam="${id}"][data-fading]::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: ${borderRadius}px;${beforeClipEarly}
  background: ${innerGradients};
  box-shadow: inset 0 0 ${innerBoxShadowBlur}px 1px ${innerShadow};
${beforeMaskBlock}
  pointer-events: none;
  z-index: 1;
  opacity: calc(var(--beam-opacity-${id}) * ${finalInnerOpacity.toFixed(2)} * var(--beam-strength, 1));${beforeClipLate}
  ${hueShiftAnimation}
}

[data-beam="${id}"] [data-beam-bloom] {
  display: none;
  position: absolute;
  inset: 0;
  border-radius: ${innerRadius}px;
  clip-path: inset(0 round ${borderRadius}px);
  background: ${bloomGradient};
  ${dualMask('linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0)', 'xor', 'exclude')}
  padding: ${borderWidth}px;
  filter: blur(8px) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)});
  pointer-events: none;
  z-index: 3;
  opacity: 0;
}

[data-beam="${id}"][data-active] [data-beam-bloom],
[data-beam="${id}"][data-fading] [data-beam-bloom] {
  display: block;
  opacity: calc(var(--beam-opacity-${id}) * ${finalBloomOpacity.toFixed(2)} * var(--beam-strength, 1));
}

@keyframes beam-spin-${id} {
  to { --beam-angle-${id}: 360deg; }
}

@keyframes beam-fade-in-${id} {
  to { --beam-opacity-${id}: 1; }
}

@keyframes beam-fade-out-${id} {
  from { --beam-opacity-${id}: 1; }
  to { --beam-opacity-${id}: 0; }
}
${hueShiftKeyframes}
${pausedAnimationsRule(id)}
`;
}

// The traveling-stroke conic mask shared by the ::after of both 'sm' and 'md' —
// full 2-space-indented `-webkit-mask`/`mask` declaration block.
function borderlikeAfterMaskBlock(id: string): string {
  const image = `conic-gradient(
      from var(--beam-angle-${id}),
      transparent 0%, transparent 30%,
      rgba(255, 255, 255, 0.1) 36%, rgba(255, 255, 255, 0.35) 44%,
      white 52%, white 80%,
      rgba(255, 255, 255, 0.35) 86%, rgba(255, 255, 255, 0.1) 92%,
      transparent 95%, transparent 100%
    ),
    linear-gradient(#fff 0 0) content-box,
    linear-gradient(#fff 0 0)`;
  return `  -webkit-mask:
    ${image};
  -webkit-mask-composite: source-in, xor;
  mask:
    ${image};
  mask-composite: intersect, exclude;`;
}

function buildSmConfig(options: GenerateStylesOptions): BorderlikeConfig {
  const { id, colors } = options;
  // Small variant uses wider mask to show more of the beam around the smaller element
  const smallMask = `conic-gradient(
    from var(--beam-angle-${id}),
    transparent 0%, transparent 22%,
    rgba(255, 255, 255, 0.12) 28%, rgba(255, 255, 255, 0.4) 36%,
    white 46%, white 82%,
    rgba(255, 255, 255, 0.4) 88%, rgba(255, 255, 255, 0.12) 94%,
    transparent 97%, transparent 100%
  )`;
  const beforeMaskBlock = `  -webkit-mask-image: ${smallMask};
  -webkit-mask-composite: source-over;
  mask-image: ${smallMask};
  mask-composite: add;`;
  return {
    colorGradients: getSmallColorGradients(colors),
    innerGradients: getSmallInnerGradients(colors),
    afterMaskBlock: borderlikeAfterMaskBlock(id),
    beforeMaskBlock,
    innerBoxShadowBlur: 5,
    beforeClipPathEarly: true,
  };
}

function buildMdConfig(options: GenerateStylesOptions): BorderlikeConfig {
  const { id, colors } = options;
  const innerMask = `conic-gradient(
      from var(--beam-angle-${id}),
      transparent 0%, transparent 30%,
      rgba(255, 255, 255, 0.1) 36%, rgba(255, 255, 255, 0.35) 44%,
      white 52%, white 80%,
      rgba(255, 255, 255, 0.35) 86%, rgba(255, 255, 255, 0.1) 92%,
      transparent 95%, transparent 100%
    ),
    linear-gradient(white, transparent 28px, transparent calc(100% - 28px), white),
    linear-gradient(to right, white, transparent 28px, transparent calc(100% - 28px), white)`;
  const beforeMaskBlock = `  -webkit-mask-image:
    ${innerMask};
  -webkit-mask-composite: source-in, source-over;
  mask-image:
    ${innerMask};
  mask-composite: intersect, add;`;
  return {
    colorGradients: getColorGradients(colors),
    innerGradients: getInnerGradients(colors),
    afterMaskBlock: borderlikeAfterMaskBlock(id),
    beforeMaskBlock,
    innerBoxShadowBlur: 9,
    beforeClipPathEarly: false,
  };
}

function generateLineVariantCSS(options: GenerateStylesOptions): string {
  const {
    id,
    borderRadius,
    borderWidth,
    duration,
    strokeOpacity,
    innerOpacity,
    bloomOpacity,
    innerShadow,
    staticColors,
    brightness,
    saturation,
    hueRange,
    colors,
    theme,
    manualProgress = false,
  } = options;

  const innerRadius = Math.max(0, borderRadius - borderWidth);
  const isDark = theme === 'dark';

  // When the position is driven externally (progress sweep), drop the looping
  // `beam-travel` (position/width) and `beam-edge-fade` (end fade) tracks so the
  // glow holds full opacity and sits wherever the consumer sets `--beam-x-<id>`.
  // `--beam-w`/`--beam-edge` fall back to their @property initial values (1).
  const travelTracks = manualProgress
    ? ''
    : `beam-travel-${id} ${duration}s linear infinite,
    beam-edge-fade-${id} ${duration}s linear infinite,
    `;
  
  const finalStrokeOpacity = strokeOpacity;
  const finalInnerOpacity = innerOpacity;
  const finalBloomOpacity = bloomOpacity;
  
  const hueShiftAnimation = staticColors 
    ? '' 
    : `animation: beam-hue-shift-${id} 12s ease-in-out infinite;`;

  const hueShiftBloomAnimation = staticColors
    ? ''
    : `animation: beam-hue-shift-bloom-${id} 8s ease-in-out infinite;`;
  
  const hueShiftKeyframes = staticColors ? '' : `
@keyframes beam-hue-shift-${id} {
  0% { filter: hue-rotate(-${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  50% { filter: hue-rotate(${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  100% { filter: hue-rotate(-${hueRange}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
}

@keyframes beam-hue-shift-bloom-${id} {
  0% { filter: blur(8px) hue-rotate(-${hueRange + 10}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  50% { filter: blur(8px) hue-rotate(${hueRange + 10}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
  100% { filter: blur(8px) hue-rotate(-${hueRange + 10}deg) brightness(${brightness.toFixed(2)}) saturate(${saturation.toFixed(2)}); }
}`;

  const whiteHighlight = isDark
    ? `radial-gradient(
        ellipse calc(24px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(28px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) calc(100% + 2px),
        rgba(255, 255, 255, 0.38) 0%,
        rgba(255, 255, 255, 0.12) 30%,
        transparent 65%
      )`
    : `radial-gradient(
        ellipse calc(24px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(28px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) calc(100% + 2px),
        rgba(255, 255, 255, 1.0) 0%,
        rgba(255, 255, 255, 0.5) 30%,
        transparent 65%
      )`;

  const colorGradients = getLineColorGradients(isDark, id, colors);
  const innerGradients = getLineInnerGradients(id, colors);

  const bloomGradients = getLineBloomGradients(isDark, id, colors);
  const monoBloomBlur = "";

  return `
${propReg(`beam-x-${id}`, '<number>', '0')}

${propReg(`beam-w-${id}`, '<number>', '1')}

${propReg(`beam-h-${id}`, '<number>', '1')}

${propReg(`beam-spike-${id}`, '<number>', '1')}

${propReg(`beam-spike2-${id}`, '<number>', '1')}

${propReg(`beam-edge-${id}`, '<number>', '1')}

${propReg(`beam-opacity-${id}`, '<number>', '0')}

[data-beam="${id}"] {
  position: relative;
  border-radius: ${borderRadius}px;
  overflow: hidden;
}

[data-beam="${id}"][data-active] {
  animation:
    ${travelTracks}beam-breathe-${id} ${(duration * 1.3).toFixed(1)}s ease-in-out infinite,
    beam-spike-${id} ${(duration * 1.33).toFixed(1)}s ease-in-out infinite,
    beam-spike2-${id} ${(duration * 1.7).toFixed(1)}s ease-in-out infinite,
    beam-fade-in-${id} 0.6s ease forwards;
}

[data-beam="${id}"][data-fading] {
  animation:
    ${travelTracks}beam-breathe-${id} ${(duration * 1.3).toFixed(1)}s ease-in-out infinite,
    beam-spike-${id} ${(duration * 1.33).toFixed(1)}s ease-in-out infinite,
    beam-spike2-${id} ${(duration * 1.7).toFixed(1)}s ease-in-out infinite,
    beam-fade-out-${id} 0.5s ease forwards;
}

[data-beam="${id}"][data-active]::after,
[data-beam="${id}"][data-fading]::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: ${innerRadius}px;
  padding: ${borderWidth}px;
  clip-path: inset(0 round ${borderRadius}px);
  background: ${whiteHighlight}, ${colorGradients};
  -webkit-mask:
    radial-gradient(
      ellipse calc(78px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(60px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
      white 0%, rgba(255, 255, 255, 0.5) 45%, transparent 100%
    ),
    linear-gradient(#fff 0 0) content-box,
    linear-gradient(#fff 0 0);
  -webkit-mask-composite: source-in, xor;
  mask:
    radial-gradient(
      ellipse calc(78px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(60px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
      white 0%, rgba(255, 255, 255, 0.5) 45%, transparent 100%
    ),
    linear-gradient(#fff 0 0) content-box,
    linear-gradient(#fff 0 0);
  mask-composite: intersect, exclude;
  pointer-events: none;
  z-index: 2;
  opacity: calc(var(--beam-opacity-${id}) * var(--beam-edge-${id}) * ${finalStrokeOpacity.toFixed(2)} * var(--beam-strength, 1));
  ${hueShiftAnimation}
}

[data-beam="${id}"][data-active]::before,
[data-beam="${id}"][data-fading]::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: ${borderRadius}px;
  background: ${innerGradients};
  box-shadow: inset 0 0 9px 1px ${innerShadow};
  -webkit-mask-image:
    radial-gradient(
      ellipse calc(78px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(60px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
      white 0%, rgba(255, 255, 255, 0.5) 45%, transparent 100%
    ),
    linear-gradient(white, transparent 28px, transparent calc(100% - 28px), white),
    linear-gradient(to right, white, transparent 28px, transparent calc(100% - 28px), white);
  -webkit-mask-composite: source-in, source-over;
  mask-image:
    radial-gradient(
      ellipse calc(78px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(60px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
      white 0%, rgba(255, 255, 255, 0.5) 45%, transparent 100%
    ),
    linear-gradient(white, transparent 28px, transparent calc(100% - 28px), white),
    linear-gradient(to right, white, transparent 28px, transparent calc(100% - 28px), white);
  mask-composite: intersect, add;
  pointer-events: none;
  z-index: 1;
  opacity: calc(var(--beam-opacity-${id}) * var(--beam-edge-${id}) * ${finalInnerOpacity.toFixed(2)} * var(--beam-strength, 1));
  clip-path: inset(0 round ${borderRadius}px);
  ${hueShiftAnimation}
}

[data-beam="${id}"] [data-beam-bloom] {
  display: none;
  position: absolute;
  inset: 0;
  border-radius: ${innerRadius}px;
  clip-path: inset(0 round ${borderRadius}px);
  padding: 0;
  -webkit-mask: radial-gradient(
    ellipse calc(84px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(110px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
    white 0%, rgba(255, 255, 255, 0.5) 35%, transparent 100%
  );
  -webkit-mask-composite: source-over;
  mask: radial-gradient(
    ellipse calc(84px * calc(var(--beam-w-${id}) * var(--beam-fit-w, 1))) calc(110px * calc(var(--beam-h-${id}) * var(--beam-fit-h, 1))) at calc(var(--beam-x-${id}) * (100% - 42px) + 21px) 100%,
    white 0%, rgba(255, 255, 255, 0.5) 35%, transparent 100%
  );
  mask-composite: add;
  background: ${bloomGradients};
  ${monoBloomBlur}
  pointer-events: none;
  z-index: 3;
  opacity: 0;
}

[data-beam="${id}"][data-active] [data-beam-bloom],
[data-beam="${id}"][data-fading] [data-beam-bloom] {
  display: block;
  opacity: calc(var(--beam-opacity-${id}) * var(--beam-edge-${id}) * ${finalBloomOpacity.toFixed(2)} * var(--beam-strength, 1));
  ${hueShiftBloomAnimation}
}

@keyframes beam-travel-${id} {
  0%   { --beam-x-${id}: 1;     --beam-w-${id}: 0.5; }
  10%  { --beam-x-${id}: 0.898; --beam-w-${id}: 0.8; }
  20%  { --beam-x-${id}: 0.784; --beam-w-${id}: 1.1; }
  30%  { --beam-x-${id}: 0.67;  --beam-w-${id}: 1.3; }
  40%  { --beam-x-${id}: 0.568; --beam-w-${id}: 1.45; }
  50%  { --beam-x-${id}: 0.5;   --beam-w-${id}: 1.5; }
  60%  { --beam-x-${id}: 0.432; --beam-w-${id}: 1.45; }
  70%  { --beam-x-${id}: 0.33;  --beam-w-${id}: 1.3; }
  80%  { --beam-x-${id}: 0.216; --beam-w-${id}: 1.1; }
  90%  { --beam-x-${id}: 0.102; --beam-w-${id}: 0.8; }
  100% { --beam-x-${id}: 0;     --beam-w-${id}: 0.5; }
}

@keyframes beam-edge-fade-${id} {
  0%   { --beam-edge-${id}: 0; }
  5%   { --beam-edge-${id}: 0; }
  14%  { --beam-edge-${id}: 1; }
  86%  { --beam-edge-${id}: 1; }
  95%  { --beam-edge-${id}: 0; }
  100% { --beam-edge-${id}: 0; }
}

@keyframes beam-breathe-${id} {
  0%, 100% { --beam-h-${id}: 0.8; }
  25%      { --beam-h-${id}: 1.25; }
  55%      { --beam-h-${id}: 0.85; }
  80%      { --beam-h-${id}: 1.3; }
}

@keyframes beam-spike-${id} {
  0%   { --beam-spike-${id}: 0.8; }
  25%  { --beam-spike-${id}: 1.3; }
  50%  { --beam-spike-${id}: 0.9; }
  75%  { --beam-spike-${id}: 1.4; }
  100% { --beam-spike-${id}: 0.8; }
}

@keyframes beam-spike2-${id} {
  0%   { --beam-spike2-${id}: 1.2; }
  25%  { --beam-spike2-${id}: 0.7; }
  50%  { --beam-spike2-${id}: 1.4; }
  75%  { --beam-spike2-${id}: 0.8; }
  100% { --beam-spike2-${id}: 1.2; }
}

@keyframes beam-fade-in-${id} {
  to { --beam-opacity-${id}: 1; }
}

@keyframes beam-fade-out-${id} {
  from { --beam-opacity-${id}: 1; }
  to { --beam-opacity-${id}: 0; }
}
${hueShiftKeyframes}
${pausedAnimationsRule(id)}
`;
}
