<!--
  @component Presentational row for one taggable assistant: its emoji on a swatch tinted
  with the assistant's two color stops, display name, `@handle` in the first of them, and a
  one-line description. When checked, the swatch darkens and carries the check over the emoji.

  Deliberately renders no interactive element of its own so it can sit inside whatever
  wrapper the surrounding list needs — a `DropdownMenuCheckboxItem` in `AssistantMenuListItem`,
  or a plain option row in `AssistantMentionPopup`.

  ## Usage
  ```svelte
  <AssistantRow assistant={entry.assistant} emoji={entry.emoji} colors={entry.colors} checked={entry.active}/>
  ```
-->
<script lang="ts">
    import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import type {BeamColors} from '$lib/components/ui/border-beam/types.js';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';

    interface Props {
        /** The assistant to render. */
        assistant: AiAssistant;
        /** Emoji shown at the start of the row, from the assistant's appearance. */
        emoji: string;
        /** The assistant's two color stops, from its appearance. The pair tints
         *  the swatch; `from` alone tints the handle. */
        colors: BeamColors;
        /** When true, the swatch darkens and a check is laid over the emoji. */
        checked?: boolean;
    }

    const {assistant, emoji, colors, checked = false}: Props = $props();
</script>

<span class="assistant-row" style:--assistant-from={colors.from} style:--assistant-to={colors.to}>
    <!-- The name sits right next to it, so the emoji is decoration rather than content. -->
    <span class={['assistant-row-emoji', checked && 'assistant-row-emoji--checked']} aria-hidden="true">
        <span class="assistant-row-glyph">{emoji}</span>
        {#if checked}
            <span class="assistant-row-check"><Tick02Icon size={16}/></span>
        {/if}
    </span>
    <span class="assistant-row-text">
        <span class="assistant-row-label">
            {assistant.label}
            <span class="assistant-row-handle">{assistant.handle}</span>
        </span>
        <span class="assistant-row-description">{assistant.description}</span>
    </span>
</span>

<style>
    .assistant-row {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        flex: 1;
    }

    /*
      The swatch is what makes the color coding readable at a glance: emoji hues are fixed
      and don't line up with the assistant's own color, so the tint has to come from behind
      the glyph rather than from it.
    */
    .assistant-row-emoji {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        /* A fixed square keeps every row's swatch identical; the gap between it and the
           centred 1rem emoji is what reads as the inner padding. */
        width: calc(0.25rem * 8);
        height: calc(0.25rem * 8);
        /* `--corner-sm` would land at three quarters of this box and swell it toward a
           circle — a step down keeps it a rounded rect. */
        border-radius: var(--corner-xs);
        /*
          Both stops at once, so a swatch carries the same pair the assistant's beam sweeps
          between rather than just one end of it — mixed into one flat tone rather than
          swept across the square. Low alpha over whatever surface it lands on — a menu
          row, the composer — keeps it a tint rather than a fill.
        */
        background-color: color-mix(
            in oklab,
            color-mix(in oklab, var(--assistant-from), var(--assistant-to)) 15%,
            transparent
        );
        /* Emoji carry their own color, so the box's font metrics — not the `size` the SVG
           glyphs took — decide how big they render. */
        font-size: var(--font-size-sm);
        line-height: 1;
    }

    .assistant-row-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
        /* The two lines are one unit — the name and what it does — so they sit tighter
           than the surrounding menu's default leading. */
        line-height: var(--line-height-tight);
    }

    /* Leading is set on the lines themselves, not just the column: the surrounding menu
       row sets its own, and an inherited value loses to it. */
    .assistant-row-label {
        display: flex;
        align-items: baseline;
        gap: var(--space-1, 0.25rem);
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: var(--line-height-tight);
    }

    /*
      Shifted rather than used neat: the palette sits high and bright so it glows well, and
      at that lightness it reads washed out as text. Moving lightness while keeping chroma
      and hue re-seats the tint against the surface without turning it grey the way mixing
      toward the text color would — down on light chrome, up on dark.
    */
    .assistant-row-handle {
        color: oklch(from var(--assistant-from) calc(l + var(--assistant-row-handle-shift, -0.18)) c h);
    }

    /* On dark chrome the deepening that keeps the handle legible on white is what buries
       it, so the same stop is lifted instead — identical hue and chroma either way. The
       check takes the shift too, since it is tinted from the same stop. */
    :global(html.darkMode) .assistant-row-handle,
    :global(html.darkMode) .assistant-row-check {
        --assistant-row-handle-shift: 0.12;
    }

    .assistant-row-description {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: var(--line-height-tight);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    /*
      Selected rows say so on the swatch itself rather than at the far end of the row: the
      tint deepens to roughly triple strength so the square reads as filled, and the check
      covers it. Mixed toward the raised surface rather than toward `transparent`, so the
      fill is opaque and the emoji beneath it does not show through at all.
    */
    .assistant-row-emoji--checked {
        background-color: color-mix(
            in oklab,
            color-mix(in oklab, var(--assistant-from), var(--assistant-to)) 45%,
            var(--color-surface-raised)
        );
    }

    .assistant-row-glyph {
        display: inline-flex;
        line-height: 1;
    }

    /* Same lightness-shifted stop as the handle, so the mark belongs to the assistant
       rather than to the chrome — and reads on the deepened tint either way. Carries the
       swatch fill itself so it hides the emoji outright instead of veiling it. */
    .assistant-row-check {
        position: absolute;
        border-radius: inherit;
        background-color: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        inset: 0;
        color: oklch(from var(--assistant-from) calc(l + var(--assistant-row-handle-shift, -0.18)) c h);
    }
</style>
