<!--
  @component Stand-in for a builder step's sidebar icon once the step is done:
  a bare tick in the icon's slot and colour (same path and weight as the
  iconset's Tick02), drawing itself in when it appears.
-->
<svg class="step-tick" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M5 14L8.5 17.5L19 6.5" pathLength="1"/>
</svg>

<style>
    .step-tick {
        width: 100%;
        height: 100%;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 1;
        stroke-dashoffset: 0;
        animation: tick-draw var(--duration-fast) var(--easing-out) backwards;
    }

    @keyframes tick-draw {
        from { stroke-dashoffset: 1; }
    }

    @media (prefers-reduced-motion: reduce) {
        .step-tick {
            animation: none;
        }
    }
</style>
