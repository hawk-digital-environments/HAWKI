<script lang="ts">

    import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
    import {Wrench} from '@lucide/svelte';
    import {aiToolStore} from '$lib/stores/AiToolStore.svelte.js';

    interface Props {
        tool: AiTool;
        size?: number;
    }

    const {tool, size = 16}: Props = $props();

    const capability = $derived.by(() => aiToolStore.capabilities.find(cap => cap.id === tool.capability_key));
</script>

<span class="tool-icon" style="width: {size}px; height: {size}px;">
    {#if capability?.icon_path}
        {#if capability?.icon_path.startsWith('data:image/svg+xml;base64,')}
            <span class="tool-icon-svg">
                {@html (atob(capability?.icon_path.slice(26)))}
            </span>
        {:else}
            <img src={capability?.icon_path} alt="" width={size} height={size}/>
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
