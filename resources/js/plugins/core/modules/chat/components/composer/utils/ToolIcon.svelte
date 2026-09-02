<!--
  @component Inline icon for an AI tool or capability.

  Renders the tool's own icon when the tool is a capability carrying an
  `icon_path` — either an inline base64 SVG (decoded and injected as
  `@html` so it inherits `currentColor`) or a plain image URL. For tools
  without a custom icon (i.e. concrete tools that aren't capabilities), falls
  back to the `ToolboxIcon` glyph. The glyph box is sized with `style`
  inline so callers can request any pixel size.

  Pass `swatch` to seat the glyph on the same tinted rounded square the assistant
  emoji sits on in `AssistantRow`, so a tool row and an assistant row read as the
  same kind of thing. List and detail rows want it; the composer chips don't —
  `MentionChip` renders its emoji bare too, because the whole pill is already tinted.

  @example
  <ToolIcon tool={selectedTool} size={16} />
  <ToolIcon tool={entry.tool} swatch />
-->
<script lang="ts">

    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';
    import ToolboxIcon from '$lib/components/ui/icons/iconset/ToolboxIcon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';

    interface Props {
        tool: AiToolOrCapability;
        size?: number;
        /** Seats the glyph on the tinted square shared with the assistant emoji swatch. */
        swatch?: boolean;
        /** Swatch only: darkens the tint and lays a check over the glyph, the way a
         *  selected assistant row marks itself. */
        checked?: boolean;
    }

    const {tool, size = 16, swatch = false, checked = false}: Props = $props();
</script>

{#snippet glyph()}
    <span class="tool-icon" style="width: {size}px; height: {size}px;">
        {#if tool?.is_capability}
            {#if tool?.icon_path.startsWith('data:image/svg+xml;base64,')}
                <span class="tool-icon-svg">
                    {@html (atob(tool?.icon_path.slice(26)))}
                </span>
            {:else}
                <img src={tool?.icon_path} alt="" width={size} height={size}/>
            {/if}
        {:else}
            <ToolboxIcon size={size}/>
        {/if}
    </span>
{/snippet}

{#if swatch}
    <!-- The tool's name sits right next to it, so the icon is decoration rather than content. -->
    <span class={['tool-icon-swatch', checked && 'tool-icon-swatch--checked']} aria-hidden="true">
        {@render glyph()}
        {#if checked}
            <span class="tool-icon-check"><Tick02Icon size={16}/></span>
        {/if}
    </span>
{:else}
    {@render glyph()}
{/if}

<style>
    .tool-icon {
        display: inline-flex;
        /* Fixed pixel box: never let a flex row squeeze the glyph. */
        flex-shrink: 0;
    }

    .tool-icon .tool-icon-svg > :global(svg) {
        stroke: currentColor;
    }

    .tool-icon img,
    .tool-icon .tool-icon-svg {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /*
      The counterpart to `.assistant-row-emoji`: same box, same radius, same low-alpha
      diagonal tint, so the two menus share one rhythm and one weight. Only the color
      differs — assistants are color-coded because the tint says *who* you are addressing,
      and tools carry no such identity, so theirs stays neutral per the token file's rule
      that chrome is neutral and chroma is reserved for accent and status.
    */
    .tool-icon-swatch {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        /* A fixed square keeps every row's swatch identical; the gap between it and the
           centred 16px glyph is what reads as the inner padding. Matches the assistant
           swatch exactly — its emoji renders at `--font-size-sm`, which is this 16px. */
        width: calc(0.25rem * 8);
        height: calc(0.25rem * 8);
        /* `--corner-sm` would land at three quarters of this box and swell it toward a
           circle — a step down keeps it a rounded rect, as on the assistant swatch. */
        border-radius: var(--corner-xs);
        /*
          Blue rather than neutral, so a tool swatch reads as the tool counterpart of the
          assistant's color-coded one instead of as disabled chrome. `--color-accent-text`
          is the accent stop that is already tuned per theme — deep blue on light chrome,
          light blue on dark — so the same mix holds up in both.
        */
        background-color: color-mix(in oklab, var(--color-accent-text) 15%, transparent);
        /* The glyph inherits the same blue, so swatch and icon read as one mark. */
        color: var(--color-accent-text);
        line-height: 0;
    }

    /*
      Selection is marked on the swatch, matching `AssistantRow`: the tint deepens to
      roughly triple strength and the check covers the glyph. Mixed toward the raised
      surface rather than toward `transparent`, so the fill is opaque.
    */
    .tool-icon-swatch--checked {
        background-color: color-mix(in oklab, var(--color-accent-text) 35%, var(--color-surface-raised));
    }

    .tool-icon-check {
        position: absolute;
        border-radius: inherit;
        background-color: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        inset: 0;
        color: var(--color-accent-text);
    }
</style>

