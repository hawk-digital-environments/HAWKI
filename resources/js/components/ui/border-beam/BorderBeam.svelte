<!--
  @component Animated border beam effect. Wraps content with a traveling
  (`sm`/`md`/`line`) glow rendered entirely from generated CSS. The wrapper's
  first child is the visible content; the beam paints in `::before`/`::after`
  and a `[data-beam-bloom]` layer, all `pointer-events: none`.

  Each instance generates a global stylesheet keyed by a unique id and injected
  via `{@html}`; the wrapper carries `data-beam={id}` so the rules target it.

  @example
  ```svelte
  <BorderBeam size="md">
      <div class="card">Content</div>
  </BorderBeam>
  ```
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import {untrack} from 'svelte';
    import type {BorderBeamSize, BorderBeamTheme} from './types';
    import {generateBeamCSS, sizePresets, sizeThemePresets} from './styles';
    import {type AppTheme, themeStore} from '$lib/stores/ThemeStore.svelte.js';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'children'> {
        /** Content to wrap with the border beam effect. */
        children: Snippet;
        /**
         * Size/type preset: 'sm' (compact), 'md' (full border, default), 'line' (bottom traveling).
         * @default 'md'
         */
        size?: BorderBeamSize;
        /** Adapts beam/glow colors for dark or light backgrounds; 'auto' follows the system. @default 'dark' */
        theme?: BorderBeamTheme;
        /** Disable the hue-shift animation for static colors (e.g. monochrome). @default false */
        staticColors?: boolean;
        /** Rotation/travel duration in seconds. Defaults per size (1.96 / 3.1). */
        duration?: number;
        /** Whether the animation is active; toggling fades the effect in/out. @default true */
        active?: boolean;
        /** Custom border radius in px. Auto-detected from the first child when omitted. */
        borderRadius?: number;
        /** Brightness multiplier for the glow. Falls back to the type's preset default (1.3 for most). */
        brightness?: number;
        /** Saturation multiplier for the glow. Defaults per theme. */
        saturation?: number;
        /** Hue rotation range in degrees for the hue-shift animation. @default 30 */
        hueRange?: number;
        /** Overall strength/opacity of the effect (0–1). Only affects the beam layers, not the children. @default 1 */
        strength?: number;
        /**
         * 'line' only: drive the traveling glow's horizontal position (0 → left,
         * 1 → right) instead of looping. When set, the beam makes a single
         * externally-controlled pass — e.g. a progress sweep tracking an async
         * task. Omit (or pass `null`) for the default looping travel.
         */
        progress?: number | null;
        /** Called when the fade-in animation completes. */
        onActivate?: () => void;
        /** Called when the fade-out animation completes. */
        onDeactivate?: () => void;
        /** The wrapper `<div>`, exposed for `bind:ref`. */
        ref?: HTMLDivElement | null;
    }

    let {
        children,
        size = 'md',
        theme = 'dark',
        staticColors = false,
        duration,
        active = true,
        borderRadius: customBorderRadius,
        brightness: brightnessProp,
        saturation,
        hueRange = 30,
        strength = 1,
        progress = null,
        class: className,
        style,
        onActivate,
        onDeactivate,
        onanimationend: consumerOnAnimationEnd,
        ref = $bindable(null),
        ...restProps
    }: Props = $props();

    // Unique, CSS-/selector-safe id scoping this instance's generated stylesheet
    // and custom properties (the React version used useId() + a colon strip).
    const baseId = $props.id();
    const id = baseId.replace(/[^a-zA-Z0-9_-]/g, '-');

    // ── System theme tracking (only meaningful when theme="auto") ──────────────
    let systemTheme = $derived.by(() => {
        if (theme === 'auto') {
            return themeStore.theme;
        }
        return theme;
    });

    // ── Instance state ─────────────────────────────────────────────────────────
    // Seed from the initial `active` prop (cf. React's useState(active)); later
    // changes are synced by the active/fading effect below, so capturing just the
    // initial value here is intentional.
    let isActive = $state(untrack(() => active));
    let isFading = $state(false);
    let isVisible = $state(true);
    let detectedRadius = $state<number | null>(null);
    // Per-axis scale that keeps the traveling glow blobs (`md`/`line`) visually
    // proportional on elements larger or smaller than the geometry's reference.
    let beamFit = $state<{ w: number; h: number; fill: number }>({w: 1, h: 1, fill: 1});

    // Auto-detect child border radius when no explicit value is provided.
    $effect(() => {
        if (customBorderRadius != null) return;
        const el = ref;
        if (!el) return;

        const detect = () => {
            const child = el.firstElementChild as HTMLElement | null;
            if (!child) return;
            const computed = getComputedStyle(child);
            const raw = parseFloat(computed.borderTopLeftRadius);
            if (!isNaN(raw) && raw > 0) {
                detectedRadius = raw;
            }
        };

        detect();

        // Re-detect if child layout changes (e.g. CSS loaded late).
        const observer = new MutationObserver(detect);
        observer.observe(el, {childList: true, subtree: false});
        return () => observer.disconnect();
    });

    // Sync the external `active` prop into the internal active/fading state so the
    // fade-out animation can run to completion before the beam is fully removed.
    $effect(() => {
        if (active && !isActive && !isFading) {
            isActive = true;
        } else if (!active && isActive && !isFading) {
            isFading = true;
        }
    });

    // Pause the (paint-heavy) animations while the element is scrolled offscreen.
    // This stops per-frame painting entirely for hidden instances without changing
    // their logical active/fading state, so it never fires onActivate/onDeactivate.
    $effect(() => {
        const el = ref;
        if (!el || typeof IntersectionObserver === 'undefined') return;

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) isVisible = entry.isIntersecting;
            },
            // Start animating slightly before the element scrolls into view.
            {rootMargin: '256px'}
        );

        observer.observe(el);
        return () => observer.disconnect();
    });

    // The 'md' and 'line' beams author their glow/tint blobs in fixed pixels for a
    // ~350x140 reference element. On a much larger surface like the chat composer
    // the blobs shrink to small dots — the traveling window reveals mostly the
    // white gradient with little color ('md'), or a tiny dot crawls the edge
    // ('line'). Measure the wrapped element and scale the blob geometry per-axis
    // (via --beam-fit-w/-h) so it stays proportional.
    $effect(() => {
        if (size !== 'md' && size !== 'line') {
            beamFit = {w: 1, h: 1, fill: 1};
            return;
        }

        const el = ref;
        if (!el) return;

        const REF_WIDTH = 350;
        const REF_HEIGHT = 140;
        const MIN_SCALE = 0.6;
        // Softened cap: on large surfaces (e.g. the chat composer) a high cap
        // inflates the blobs toward a uniform ring and washes out the authored
        // size variation that reads well on the smaller full-border-glow card.
        // Pulled down so big surfaces still gain coverage but keep more variation.
        const MAX_SCALE = 1.6;
        const clamp = (value: number) => Math.max(MIN_SCALE, Math.min(MAX_SCALE, value));

        const measure = () => {
            const child = el.firstElementChild as HTMLElement | null;
            if (!child) return;
            const rect = child.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            const rawW = rect.width / REF_WIDTH;
            const rawH = rect.height / REF_HEIGHT;
            const w = +clamp(rawW).toFixed(3);
            const h = +clamp(rawH).toFixed(3);
            // Extra growth so the fixed-position blobs overlap into a continuous
            // ring on large surfaces instead of reading as detached patches. Keyed
            // off the uncapped ratio (which `w`/`h` cap away) so a wide composer
            // still gets the fill even once the per-axis fit is maxed out.
            const fill = +Math.max(1, Math.min(1.35, 1 + (Math.max(rawW, rawH) - 1) * 0.3)).toFixed(3);
            if (beamFit.w !== w || beamFit.h !== h || beamFit.fill !== fill) {
                beamFit = {w, h, fill};
            }
        };

        measure();
        if (typeof ResizeObserver === 'undefined') return;

        const child = el.firstElementChild as HTMLElement | null;
        if (!child) return;

        const resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(child);
        return () => resizeObserver.disconnect();
    });

    // ── Derived configuration ──────────────────────────────────────────────────
    const resolvedTheme: AppTheme = $derived(theme === 'auto' ? systemTheme : theme);
    const themeConfig = $derived(sizeThemePresets[size][resolvedTheme]);
    const sizeConfig = $derived(sizePresets[size]);

    const finalBorderRadius = $derived(customBorderRadius ?? detectedRadius ?? sizeConfig.borderRadius);
    const finalDuration = $derived(duration ?? (size === 'line' ? 3.1 : 1.96));
    const finalSaturation = $derived(saturation ?? themeConfig.saturation);
    const finalBrightness = $derived(brightnessProp ?? themeConfig.brightness ?? 1.3);
    // 'hawki' is a single-hue blue brand palette; keep the hue oscillation tight so
    // it never drifts into cyan/teal (negative rotation) or violet (positive).
    const baseHueRange = $derived(Math.min(hueRange, 10));
    const finalHueRange = $derived(size === 'line' ? Math.min(baseHueRange, 13) : baseHueRange);
    const clampedStrength = $derived(Math.max(0, Math.min(1, strength)));
    // Externally-driven single pass (line only): disables the looping travel track
    // and lets the effect below position the glow via `--beam-x-<id>`.
    const manualProgress = $derived(size === 'line' && progress != null);

    // Mirror the `progress` prop (0 → left, 1 → right) onto the id-scoped custom
    // property the line gradients read. Set imperatively because the property name
    // is dynamic (carries the instance id).
    $effect(() => {
        const el = ref;
        if (!el) return;
        if (!manualProgress) {
            el.style.removeProperty(`--beam-x-${id}`);
            return;
        }
        const clamped = Math.max(0, Math.min(1, progress ?? 0));
        el.style.setProperty(`--beam-x-${id}`, String(clamped));
    });

    const cssStyles = $derived(
        generateBeamCSS({
            id,
            borderRadius: finalBorderRadius,
            borderWidth: sizeConfig.borderWidth,
            duration: finalDuration,
            strokeOpacity: themeConfig.strokeOpacity,
            innerOpacity: themeConfig.innerOpacity,
            bloomOpacity: themeConfig.bloomOpacity,
            innerShadow: themeConfig.innerShadow,
            size,
            staticColors,
            brightness: finalBrightness,
            saturation: finalSaturation,
            hueRange: finalHueRange,
            theme: resolvedTheme,
            hairlineOpacity: themeConfig.hairlineOpacity,
            manualProgress
        }),
    );

    function handleAnimationEnd(e: Parameters<NonNullable<Props['onanimationend']>>[0]) {
        const animationName = e.animationName;

        if (animationName.includes('fade-out')) {
            isActive = false;
            isFading = false;
            onDeactivate?.();
        } else if (animationName.includes('fade-in')) {
            onActivate?.();
        }

        consumerOnAnimationEnd?.(e);
    }
</script>

{@html `<style>${cssStyles}</style>`}

<div
    {...restProps}
    bind:this={ref}
    data-beam={id}
    data-active={isActive && !isFading ? '' : undefined}
    data-fading={isFading ? '' : undefined}
    data-paused={isActive && !isFading && !isVisible ? '' : undefined}
    class={className}
    {style}
    style:--beam-strength={clampedStrength}
    style:--beam-fit-w={size === 'md' || size === 'line' ? beamFit.w : undefined}
    style:--beam-fit-h={size === 'md' || size === 'line' ? beamFit.h : undefined}
    style:--beam-fill={size === 'md' || size === 'line' ? beamFit.fill : undefined}
    onanimationend={handleAnimationEnd}
>
    {@render children?.()}
    <div data-beam-bloom></div>
</div>
