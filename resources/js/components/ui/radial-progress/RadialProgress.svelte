<!--
  @component Circular radial progress indicator. Renders an SVG ring that fills
  clockwise from 0 to 100 percent. Used to show per-file upload progress.

  Renders nothing (no DOM at all, not even an empty wrapper) while `value` is
  `undefined` — pass `undefined` to hide it entirely (e.g. before an upload
  starts) rather than `0`, which draws an empty ring. Uses `currentColor` for
  the indicator stroke, so it inherits the surrounding text/icon color.

  @example
  ```svelte
  {#if progress !== null}
      <RadialProgress value={progress}/>
  {/if}
  ```
-->
<script lang="ts">
    import type {SVGAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {growTransition} from '../../lib/transitions/growTransition.js';

    interface Props extends SVGAttributes<SVGElement> {
        /** Progress value, 0–100. Clamped to that range. */
        value?: number;
        /** Outer diameter of the ring in pixels. */
        size?: number;
        /** Stroke width of the ring in pixels. */
        strokeWidth?: number;
    }

    const {value, size = 14, strokeWidth = 2, ...restProps}: Props = $props();

    const clamped = $derived(Math.max(0, Math.min(100, value ?? 0)));
    const radius = $derived((size - strokeWidth) / 2);
    const circumference = $derived(2 * Math.PI * radius);
    const dashOffset = $derived(circumference * (1 - clamped / 100));
    const center = $derived(size / 2);
</script>
{#if value !== undefined}
    <svg
        transition:growTransition={{mode: 'horizontal'}}
        {...mergeProps(
            {
                class: 'radial-progress',
                width: size,
                height: size,
                viewBox: `0 0 ${size} ${size}`,
                role: 'progressbar',
                'aria-valuenow': Math.round(clamped),
                'aria-valuemin': 0,
                'aria-valuemax': 100,
            },
            restProps,
        )}
    >
        <circle
            class="radial-progress__track"
            cx={center}
            cy={center}
            r={radius}
            fill="none"
            stroke-width={strokeWidth}
        />
        <circle
            class="radial-progress__indicator"
            cx={center}
            cy={center}
            r={radius}
            fill="none"
            stroke-width={strokeWidth}
            stroke-linecap="round"
            stroke-dasharray={circumference}
            stroke-dashoffset={dashOffset}
            transform={`rotate(-90 ${center} ${center})`}
        />
    </svg>
{/if}

<style>
    .radial-progress {
        flex-shrink: 0;
    }

    .radial-progress__track {
        stroke: color-mix(in oklch, currentColor 20%, transparent);
    }

    .radial-progress__indicator {
        stroke: currentColor;
        transition: stroke-dashoffset var(--duration-fast, 200ms) var(--easing-spring, ease-out);
    }
</style>
