<!--
  @component The wizard step's numbered ring (see the sidebars' step lists):
  a track circle with the step number, an optional progress arc that fills as
  the step's required fields are answered, and — once the step is done — an
  accent disc that grows over the number while a tick draws itself in.

  Sized through the `--step-ring-size` custom property (falls back to
  `--nav-icon-size`, then 1.25rem). The size is set via an inline style on
  purpose: sidebar rows bump plain `svg` glyphs on mobile
  (see `SidebarItem`), and inline sizing keeps the ring out of that.
-->
<script lang="ts">
    interface Props {
        /** The step's one-based number, shown inside the ring. */
        number: number;
        /** Whether the step is completed: disc covers the number, tick draws in. */
        done: boolean;
        /** Fill ratio of the progress arc, 0..1; omit to render without an arc. */
        ratio?: number;
    }

    let {number, done, ratio}: Props = $props();
</script>

<svg
    class="step-ring"
    class:done
    viewBox="0 0 20 20"
    fill="none"
    aria-hidden="true"
    style:width="var(--step-ring-size, var(--nav-icon-size, 1.25rem))"
    style:height="var(--step-ring-size, var(--nav-icon-size, 1.25rem))"
>
    <circle class="track" cx="10" cy="10" r="8"/>
    {#if ratio !== undefined}
        <circle
            class="arc"
            class:empty={ratio === 0}
            cx="10" cy="10" r="8"
            pathLength="1"
            style:stroke-dashoffset={1 - ratio}
        />
    {/if}
    <text class="number" x="10" y="10">{number}</text>
    <circle class="disc" cx="10" cy="10" r="9"/>
    <path class="tick" d="M6.25 10.5L8.75 13L13.75 7.5" pathLength="1"/>
</svg>

<style>
    .step-ring {
        overflow: visible;
    }

    /* In the row's own colour, so it follows hover, active and locked rows. */
    .track {
        stroke: currentColor;
        stroke-width: 2;
        opacity: 0.28;
    }

    /* The step's number, covered by the disc once the step is done. */
    .number {
        fill: currentColor;
        font-size: 10.5px;
        font-weight: var(--font-weight-semibold);
        font-variant-numeric: tabular-nums;
        text-anchor: middle;
        dominant-baseline: central;
    }

    /* Starts at twelve o'clock and eases round as fields are answered. */
    .arc {
        stroke: var(--color-accent-fill);
        stroke-width: 2;
        stroke-linecap: round;
        stroke-dasharray: 1;
        rotate: -90deg;
        transform-origin: center;
        transition: stroke-dashoffset 420ms cubic-bezier(0.3, 0, 0.2, 1), opacity var(--duration-extra-fast);
    }

    .arc.empty {
        opacity: 0;
    }

    /* Done: the disc grows out of the ring's centre with a small overshoot,
       then the tick draws itself in. */
    .disc {
        fill: var(--color-accent-fill);
        transform-origin: center;
        scale: 0;
        transition: scale var(--duration-extra-fast) var(--easing-out);
    }

    .tick {
        stroke: var(--color-on-accent-fill);
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        transition: stroke-dashoffset var(--duration-extra-fast) var(--easing-out);
    }

    .done .disc {
        scale: 1;
        transition: scale 360ms cubic-bezier(0.3, 1.6, 0.5, 1);
    }

    .done .tick {
        stroke-dashoffset: 0;
        transition-delay: 140ms;
    }

    @media (prefers-reduced-motion: reduce) {
        .arc,
        .disc,
        .tick,
        .done .disc,
        .done .tick {
            transition: none;
        }
    }
</style>
