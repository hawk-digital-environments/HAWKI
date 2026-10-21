<!--
  @component Reveals a short string one character at a time, with a colored front that
  travels along the text: each glyph fades in tinted (`leadColor`), passes through
  `trailColor`, and settles to the surrounding `color`. The whole reveal restarts whenever
  `text` changes, so it doubles as a "this just changed" affordance.

  Meant for short, punchy strings — a tag, a handle, a status word. It renders one element
  per character, so it is not the tool for a paragraph.

  Screen readers get the string in one piece (`aria-label` on the wrapper, characters
  hidden), and the animation is skipped entirely under `prefers-reduced-motion`.

  @example
  ```svelte
  <TextReveal text={assistantHandle}/>
  ```

  @example Slower, with the accent colors swapped:
  ```svelte
  <TextReveal
      text="Ready"
      stagger={60}
      duration={900}
      leadColor="var(--color-success)"
      trailColor="var(--color-accent-300)"/>
  ```
-->
<script module lang="ts">
    /** Timing defaults, exported so callers can sequence something after the reveal. */
    export const textRevealDefaults = {
        /** Delay between consecutive characters, in ms. */
        stagger: 35,
        /** How long a single character takes to settle, in ms. */
        duration: 600
    } as const;

    /**
     * How long the full reveal of `text` takes, in ms — the last character's delay plus its
     * own duration. Use it to start a follow-up animation once the text has settled.
     */
    export function textRevealDurationMs(
        text: string,
        options?: { stagger?: number; duration?: number }
    ): number {
        const stagger = options?.stagger ?? textRevealDefaults.stagger;
        const duration = options?.duration ?? textRevealDefaults.duration;
        return Math.max(Array.from(text).length - 1, 0) * stagger + duration;
    }
</script>
<script lang="ts">
    interface Props {
        /** The string to reveal. Assigning a different value replays the animation. */
        text: string;
        /** Delay between consecutive characters, in ms. @default 35 */
        stagger?: number;
        /** How long a single character takes to settle, in ms. @default 600 */
        duration?: number;
        /** Color a character starts at — the leading edge of the reveal.
         *  @default var(--color-warning) */
        leadColor?: string;
        /** Color a character passes through before settling to the inherited text color.
         *  @default var(--color-accent-300) */
        trailColor?: string;
    }

    const {
        text,
        stagger = textRevealDefaults.stagger,
        duration = textRevealDefaults.duration,
        leadColor = 'var(--color-warning)',
        trailColor = 'var(--color-accent-300)'
    }: Props = $props();

    // `Array.from` so astral characters (emoji) stay in one piece.
    const characters = $derived(Array.from(text));
</script>

<!--
  Keyed on the text so a new string remounts the spans and the animation runs from the
  start; without it, changing the text would leave already-finished characters settled.
-->
{#key text}
    <span
        class="text-reveal"
        aria-label={text}
        style:--text-reveal-stagger="{stagger}ms"
        style:--text-reveal-duration="{duration}ms"
        style:--text-reveal-lead={leadColor}
        style:--text-reveal-trail={trailColor}>
        {#each characters as character, index (index)}
            <span
                class="text-reveal-char"
                aria-hidden="true"
                style:--text-reveal-index={index}
            >{character}</span>
        {/each}
    </span>
{/key}

<style>
    .text-reveal {
        /* Keeps the spans on one line and preserves any spaces inside the string. */
        white-space: pre;
    }

    .text-reveal-char {
        display: inline-block;
        color: var(--text-reveal-lead);
        animation: text-reveal var(--text-reveal-duration) var(--easing-default, ease) both;
        animation-delay: calc(var(--text-reveal-index) * var(--text-reveal-stagger));
    }

    @keyframes text-reveal {
        from {
            opacity: 0.35;
            color: var(--text-reveal-lead);
        }

        45% {
            opacity: 1;
            color: var(--text-reveal-trail);
        }

        to {
            opacity: 1;
            /* Hands the glyph back to whatever color the surrounding text uses. */
            color: inherit;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .text-reveal-char {
            animation: none;
            opacity: 1;
            color: inherit;
        }
    }
</style>
