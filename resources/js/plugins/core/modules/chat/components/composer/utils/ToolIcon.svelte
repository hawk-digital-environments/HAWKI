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

    import {type AiToolOrCapability, toolCategoryOf} from '$plugins/core/stores/aiToolStoreData.js';
    import MenuSwatch from '$plugins/core/modules/chat/components/composer/utils/MenuSwatch.svelte';
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

    // The swatch is tinted by the tool-picker section the tool lives in; the gray
    // MCP section tints a step lighter (the neutral strengths).
    const category = $derived(toolCategoryOf(tool));
    const categoryInk = $derived(`var(--color-tool-category-${category})`);
</script>

{#snippet glyph()}
    <span
        class="tool-icon"
        data-capability={tool.capability_key ?? undefined}
        aria-hidden="true"
        style="width: {size}px; height: {size}px;"
    >
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
    <!-- The shared swatch box (see `MenuSwatch`); the `tool-icon-swatch` class stays
         so inset rows can keep tuning its radius from outside. -->
    <MenuSwatch class="tool-icon-swatch" ink={categoryInk} neutral={category === 'mcp'} {checked} data-tool-category={category}>
        {@render glyph()}
    </MenuSwatch>
{:else}
    {@render glyph()}
{/if}

<style>
    .tool-icon {
        display: inline-flex;
        /* Fixed pixel box: never let a flex row squeeze the glyph. */
        flex-shrink: 0;
    }

    .tool-icon[data-capability] {
        color: var(--capability-color);
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

    /* On a swatch the glyph takes the section ink (set by `MenuSwatch`), not its
       own capability color. */
    :global(.tool-icon-swatch) .tool-icon {
        color: inherit;
    }
</style>

