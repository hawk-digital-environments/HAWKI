<!--
  @component Inline icon for an AI tool or capability.

  Renders the tool's own icon when the tool is a capability carrying an
  `icon_path` — either an inline base64 SVG (decoded and injected as
  `@html` so it inherits `currentColor`) or a plain image URL. For tools
  without a custom icon (i.e. concrete tools that aren't capabilities), falls
  back to the `ToolboxIcon` glyph. The wrapper span is sized with `style`
  inline so callers can request any pixel size.

  @example
  <ToolIcon tool={selectedTool} size={16} />
-->
<script lang="ts">

    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';
    import ToolboxIcon from '$lib/components/ui/icons/iconset/ToolboxIcon.svelte';

    interface Props {
        tool: AiToolOrCapability;
        size?: number;
    }

    const {tool, size = 16}: Props = $props();
</script>

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

<style>
    .tool-icon {
        display: inline-flex;
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
</style>
