<!--
  @component An indeterminate loading beam: a thin light that travels round
  the outline of its positioned ancestor — the "in use / working" affordance
  for one field or tile. It is `BorderBeam`'s small, quiet counterpart: the
  chat composer gets the travelling glow around the whole card, a single
  field gets this ring (see `AiFillReveal.svelte`, which shows it while the
  builder's AI guide may write the field).

  An overlay, not a wrapper: the span positions itself absolutely against
  the nearest positioned ancestor, paints nothing else and takes no events.
  It is decorative (`aria-hidden`) — put `aria-busy` on the element whose
  state it expresses. Mount it to show the beam, unmount it to hide it; it
  fades in and out on its own.

  Every instance joins one shared clock: its phase is seeded from
  `performance.now()` when it mounts, so all beams showing at once orbit in
  sync — including one that mounts late.

  Geometry comes from custom properties, set on the span or any ancestor:
  - `--loading-beam-radius` — the corners to hug (default `--corner-md`).
  - `--loading-beam-thickness` — the light's thickness (default `1px`).
  - `--loading-beam-offset` — draw the light this far outside the host's
    box, for a tile whose own fill would hide an inset ring (default `0px`).

  Under reduced motion the beam is hidden.

  @example
  ```svelte
  <div class="field">  {* this host carries position: relative *}
      <Input …/>
      {#if locked}
          <LoadingBeam/>
      {/if}
  </div>
  ```
-->
<script lang="ts">
    import {fade} from 'svelte/transition';

    interface Props {
        /** One orbit of the light, in ms. @default 2400 */
        duration?: number;
        /** The light's color; any CSS color. @default 'var(--color-accent-400)' */
        color?: string;
    }

    const {duration = 2400, color = 'var(--color-accent-400)'}: Props = $props();

    // Seed the phase once, when the beam appears, from the same clock every
    // instance reads — so beams shown together orbit in sync (see header).
    // Deliberately the initial `duration`: re-seeding mid-flight (a changed
    // prop) would jump the light to a new point on its orbit.
    // svelte-ignore state_referenced_locally
    const phase = Math.round(performance.now() % duration);
</script>

<span
    class="loading-beam"
    aria-hidden="true"
    style:--loading-beam-color={color}
    style:--loading-beam-duration="{duration}ms"
    style:--loading-beam-phase="-{phase}ms"
    in:fade={{duration: 300}}
    out:fade={{duration: 200}}
></span>

<style>
    /* A ring cut out of a filled box (content-box excluded from the whole
       box), so the conic light below only shows on the outline. The offset
       grows the box beyond the host, and the radius grows with it so the
       light still hugs the corners. */
    .loading-beam {
        position: absolute;
        inset: calc(-1 * var(--loading-beam-offset, 0px));
        padding: var(--loading-beam-thickness, 1px);
        border-radius: calc(var(--loading-beam-radius, var(--corner-md)) + var(--loading-beam-offset, 0px));
        overflow: hidden;
        pointer-events: none;
        mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        mask-composite: exclude;
    }

    /* A square wide enough to cover the host's diagonal, spun about the
       host's centre; its bright arc is the orbiting light. */
    .loading-beam::before {
        content: '';
        position: absolute;
        inset-block-start: 50%;
        inset-inline-start: 50%;
        width: 150%;
        aspect-ratio: 1;
        translate: -50% -50%;
        background: conic-gradient(
            transparent 0deg,
            var(--loading-beam-color, var(--color-accent-400)) 40deg,
            transparent 80deg
        );
        animation: loading-beam-orbit var(--loading-beam-duration, 2400ms) linear var(--loading-beam-phase, 0ms) infinite;
    }

    @keyframes loading-beam-orbit {
        to { rotate: 1turn; }
    }

    @media (prefers-reduced-motion: reduce) {
        .loading-beam {
            display: none;
        }
    }
</style>
