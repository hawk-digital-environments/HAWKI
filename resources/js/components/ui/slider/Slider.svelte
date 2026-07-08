<!--
  @component Range slider primitive for numeric input within a min/max range.
-->
<script lang="ts">
    import {mergeProps, Slider as SliderPrimitive, type SliderRootProps} from 'bits-ui';

    type Props = Omit<SliderRootProps, 'type'> & Partial<{
        /** Current value (0–1 or custom range). */
        value?: number;
        /** Minimum selectable value. */
        min?: number;
        /** Maximum selectable value. */
        max?: number;
        /** Step between selectable values. */
        step?: number;
        /** Called when the user changes the value. */
        onValueChange?: (value: number) => void;
        /** Whether the slider is disabled. */
        disabled?: boolean;
    }>;

    const {value = 0, min = 0, max = 1, step = 0.01, onValueChange, disabled = false, ...restProps}: Props = $props();

    let rootEl = $state<HTMLElement | null>(null);
    let hovering = $state(false);
    /** Pointer x position relative to the track, in px. */
    let pointerX = $state(0);
    /** Stepped value under the cursor while hovering. */
    let hoverValue = $state(0);

    /** Trim floating-point noise from stepped values for display. */
    function format(v: number): number {
        return Number.isInteger(v) ? v : Number(v.toFixed(2));
    }

    function handlePointerMove(event: PointerEvent) {
        if (!rootEl) return;
        const {left, width} = rootEl.getBoundingClientRect();
        pointerX = Math.min(width, Math.max(0, event.clientX - left));
        const fraction = width === 0 ? 0 : pointerX / width;
        const raw = min + fraction * (max - min);
        hoverValue = Math.min(max, Math.max(min, Math.round(raw / step) * step));
    }
</script>

<SliderPrimitive.Root
    type="single"
    {value}
    {min}
    {max}
    {step}
    {disabled}
    {onValueChange}
    {...mergeProps({
        class: 'slider-root',
        onpointerenter: () => (hovering = true),
        onpointerleave: () => (hovering = false),
        onpointermove: handlePointerMove
    }, restProps) as any}
    bind:ref={rootEl}
>
    {#snippet children({thumbs})}
        <span class="slider-track">
            <SliderPrimitive.Range class="slider-range"/>
        </span>
        {#each thumbs as thumb (thumb)}
            <SliderPrimitive.Thumb index={thumb} class="slider-thumb"/>
        {/each}
        {#if hovering && !disabled}
            <span class="slider-tooltip" style="left: {pointerX}px">{format(hoverValue)}</span>
        {/if}
    {/snippet}
</SliderPrimitive.Root>

<style>
    :global(.slider-root) {
        position: relative;
        display: flex;
        height: 20px;
        width: 100%;
        align-items: center;
        user-select: none;
        touch-action: none;
        cursor: ew-resize;
    }

    .slider-track {
        position: relative;
        height: 20px;
        width: 100%;
        overflow: hidden;
        border-radius: var(--corner-full);
        /* Neutral fill instead of the slightly blue-tinted --color-bg-secondary. */
        background-color: var(--color-surface-light);
        border: none;
    }

    :global(.slider-range) {
        position: absolute;
        height: 100%;
        border-radius: var(--corner-full);
        background-color: var(--color-hover);
    }

    :global(.slider-thumb) {
        display: block;
        height: calc(0.25rem * 4);
        width: calc(0.25rem * 4);
        border-radius: var(--corner-full);
        background-color: var(--color-surface-raised);
        box-shadow: var(--elevation-1);
        cursor: ew-resize;
        transition: box-shadow var(--duration-fast, 150ms) var(--easing-default);

        &:active {
            cursor: ew-resize;
        }

        &:focus-visible {
            outline: 2px solid var(--color-focus-ring, var(--color-interactive));
            outline-offset: 2px;
        }
    }

    :global(html.darkMode .slider-thumb) {
        background-color: var(--color-interactive);
    }

    .slider-tooltip {
        position: absolute;
        bottom: calc(100% + 0.375rem);
        transform: translateX(-50%);
        padding: 0.125rem 0.375rem;
        border-radius: var(--corner-sm);
        background-color: var(--color-surface-raised);
        border: var(--border);
        font-size: 0.75rem;
        line-height: 1;
        white-space: nowrap;
        pointer-events: none;
    }
</style>
