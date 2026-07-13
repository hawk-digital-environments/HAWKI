<!--
  @component One assistant chip: the handle reveals character by character (`TextReveal`),
  and only once it has settled does the pill itself arrive, its fill blur-scaling in behind
  the text. Owning that sequence per chip (rather than in `MentionChips`) keeps the timers
  independent, so chips added at different moments each play their own run.

  Fill and text are both painted from the assistant's own pair of color stops, so a chip
  identifies who the message is addressed to at a glance — the same colors that mark the
  assistant's row in the `@` menu and the mention popup.

  ## Usage
  Rendered by `MentionChips`, once per handle in the message:
  ```svelte
  <MentionChip handle="@hawki" label="HAWKI" emoji="🤖" colors={colors} onRemove={() => ...}/>
  ```
-->
<script lang="ts">
    import type {BeamColors} from '$lib/components/ui/border-beam/types.js';
    import TextReveal, {textRevealDurationMs} from '$lib/components/ui/text-reveal/TextReveal.svelte';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** The `@handle` this chip stands for; also the text that gets revealed. */
        handle: string;
        /** The assistant's display name, used for the remove button's label. */
        label: string;
        /** The assistant's emoji, from `getAssistantAppearance`. */
        emoji: string;
        /** The two stops the fill and the reveal are painted from, out of
         *  `getAssistantAppearance` — this is what color-codes the chip. */
        colors: BeamColors;
        /** Disables the remove button (e.g. while a send is in flight). */
        disabled?: boolean;
        /** Called when the remove button is pressed. */
        onRemove: () => void;
    }

    const {handle, label, emoji, colors, disabled = false, onRemove}: Props = $props();

    // Snappier than the component defaults: a handle is a handful of characters, so the
    // reveal should read as one quick sweep rather than a per-letter crawl.
    const revealStagger = 22;
    const revealDuration = 320;

    // How long the handle takes to finish revealing.
    const revealMs = $derived(textRevealDurationMs(handle, {stagger: revealStagger, duration: revealDuration}));
    // The pill starts just before the last characters settle. Overlapping the two beats
    // keeps the sequence tight — waiting for a clean hand-off reads as a stall.
    const pillDelayMs = $derived(Math.max(revealMs - 140, 0));

    let pillArrived = $state(false);

    $effect(() => {
        // Re-runs when the handle changes, so a swapped assistant replays the whole sequence.
        const delay = pillDelayMs;
        pillArrived = false;
        const timer = setTimeout(() => (pillArrived = true), delay);
        return () => clearTimeout(timer);
    });
</script>

<button
    type="button"
    class="mention-chip"
    class:mention-chip--arrived={pillArrived}
    aria-label={__('chat.composer.mentionChips.removeAriaLabel', {assistant: label})}
    disabled={disabled}
    onclick={onRemove}
    style:--assistant-from={colors.from}
    style:--assistant-to={colors.to}>
    <span class="mention-chip-content">
        <!-- The handle is spelled out right next to it, so the emoji is decoration. -->
        <span class="mention-chip-emoji" aria-hidden="true">{emoji}</span>
        <span class="mention-chip-handle">
            <TextReveal
                text={handle}
                stagger={revealStagger}
                duration={revealDuration}
                leadColor="var(--mention-chip-lead)"
                trailColor="var(--mention-chip-trail)"/>
        </span>
        <!-- Decorative: the whole chip is the remove control, so this must not be
             a nested button of its own. -->
        <span class="mention-chip-remove" aria-hidden="true">
            <Cancel01Icon size={16}/>
        </span>
    </span>
</button>

<style>
    .mention-chip {
        /*
          Shifted rather than used neat: the palette sits high and bright so it glows well,
          and at that lightness it reads washed out as text mid-reveal. Moving lightness
          while keeping chroma and hue re-seats the tint against the surface without turning
          it grey the way mixing toward the text color would — down on light chrome, up on
          dark (see below). Declared here so `TextReveal`'s per-character spans inherit them.
        */
        --mention-chip-lightness-shift: -0.18;
        --mention-chip-lead: oklch(from var(--assistant-from) calc(l + var(--mention-chip-lightness-shift)) c h);
        --mention-chip-trail: oklch(from var(--assistant-to) calc(l + var(--mention-chip-lightness-shift)) c h);

        position: relative;
        display: inline-flex;
        align-items: center;
        /* Sat on the beam wrapper while there was one; the chip is the flex item now. */
        flex-shrink: 0;
        /* The chip itself is the remove control, so it carries the button reset. */
        border: none;
        background: none;
        font: inherit;
        cursor: pointer;
        border-radius: var(--corner-full);
        /* Shared with the model picker the chip sits next to, so the two line up exactly
           instead of each landing wherever its own padding and line box put it. The
           padding below still describes the intended breathing room and, at this height,
           agrees with it — the 1.25rem line box plus 2 × 6px is the 2rem. */
        height: var(--chat-composer-control-height, 2rem);
        padding-inline: var(--space-3, calc(0.25rem * 3));
        padding-block: var(--space-1_5, calc(0.25rem * 1.5));
        color: var(--color-text);
        font-size: var(--font-size-xs);
        line-height: 1.25rem;
        white-space: nowrap;

        &:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
    }

    /*
      On dark chrome the deepening that keeps the handle legible on white is what buries it:
      the same stop that reads as a tint on a light surface goes muddy against a dark one.
      Lifting instead of dropping keeps the identical hue and chroma and lets the handle sit
      above the surface it's painted on, the way it does in light mode.
    */
    :global(html.darkMode) .mention-chip {
        --mention-chip-lightness-shift: 0.12;
    }

    /*
      The pill's surface lives in a pseudo-element rather than on the chip itself, so it can
      blur-scale in on its own while the text above it stays sharp and legible throughout the
      reveal that precedes it.
    */
    .mention-chip::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        /* The same tint the assistant's emoji swatch carries in the `@` menu and mention
           popup, so a tag is filled in the colors that identify it rather than in the
           neutral hover grey the assistant button used to sit on. */
        background-color: color-mix(
            in oklab,
            color-mix(in oklab, var(--assistant-from), var(--assistant-to)) 15%,
            transparent
        );
        opacity: 0;
    }

    /*
      `linear` is deliberate: a CSS timing function applies to every keyframe interval on
      its own, so an eased curve here would front-load each leg of the animation and turn
      one arrival into a run of little jolts. The pacing lives inside the keyframes instead,
      one timing function per leg — out to the peak, then back down.
    */
    .mention-chip--arrived::before {
        animation: mention-chip-blur-in 380ms linear both;
    }

    /* The content rides the same beat with a much smaller overshoot, so the chip lands as
       one object instead of a surface animating behind static text. */
    .mention-chip--arrived .mention-chip-content {
        animation: mention-chip-settle 380ms linear both;
    }

    /*
      `TextReveal` settles each character to `inherit`, so this is the color the handle is
      left in once the reveal finishes — the same darkened stop the assistant's row uses in
      the `@` menu, so a tag and its menu row read as the same thing. Without it the handle
      landed on the plain text color and the tint only existed for the length of the
      animation.
    */
    .mention-chip-handle {
        color: var(--mention-chip-lead);
    }

    .mention-chip-content {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5, calc(0.25rem * 1.5));
    }

    /*
      The box stays the 16px square the assistant's SVG glyph used to occupy while the glyph
      inside it renders larger and overflows symmetrically. Pinning both axes is what keeps
      the pill's size independent of the emoji: the box contributes a fixed 16px to the
      content row, which the handle's 1.25rem line-height already exceeds, so growing the
      emoji can't push the chip taller or wider.
    */
    .mention-chip-emoji {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1rem;
        height: 1rem;
        font-size: 1.1rem;
        line-height: 1;
    }

    .mention-chip-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: none;
        background: none;
        /*
          The handle's tint, held back so the control stays subordinate to the name it sits
          next to, and coming forward to the full tint on hover — the same muted-to-full
          move it made in the neutral greys, now in the assistant's color.
        */
        color: color-mix(in oklab, var(--mention-chip-lead) 55%, transparent);
        line-height: 0;
        transition: color var(--duration-fast, 150ms);
    }

    /* Hovering anywhere on the chip lights the X — the X is the only part that
       changes, but the whole pill is the hover target. */
    .mention-chip:hover:not(:disabled) .mention-chip-remove,
    .mention-chip:focus-visible:not(:disabled) .mention-chip-remove {
        color: var(--mention-chip-lead);
    }

    /*
      The overshoot lives in the keyframes rather than in a springy easing curve: the blur
      and opacity should finish early and stay finished, while only the scale swings past 1
      and settles back. A back-out easing applied to the whole animation would drag the blur
      along with it and read as a wobble.

      One swing, and a small one — the pill rises past its size and comes to rest. The
      earlier version also dipped back under 1 before landing, which at this size read as a
      shudder rather than as weight. Decelerating into the peak and easing out of it does
      the same job smoothly.
    */
    @keyframes mention-chip-blur-in {
        0% {
            opacity: 0;
            filter: blur(5px);
            scale: 0.88;
            animation-timing-function: var(--easing-spring, ease-out);
        }

        55% {
            opacity: 1;
            filter: blur(0);
            scale: 1.03;
            animation-timing-function: var(--easing-default, ease-in-out);
        }

        100% {
            opacity: 1;
            filter: blur(0);
            scale: 1;
        }
    }

    @keyframes mention-chip-settle {
        0% {
            scale: 0.975;
            animation-timing-function: var(--easing-spring, ease-out);
        }

        55% {
            scale: 1.012;
            animation-timing-function: var(--easing-default, ease-in-out);
        }

        100% {
            scale: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .mention-chip--arrived::before {
            animation: none;
            opacity: 1;
        }

        .mention-chip--arrived .mention-chip-content {
            animation: none;
        }
    }
</style>
