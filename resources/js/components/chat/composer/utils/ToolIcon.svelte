<script lang="ts">

    import {Wrench} from '@lucide/svelte';
    import type {AiToolOrCapability} from '$lib/stores/aiToolStoreData.js';

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
        <Wrench size={size}/>
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
