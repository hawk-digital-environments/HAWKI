<script lang="ts">
    import { onMount } from "svelte";
    import { assistantOptionsStore } from "$lib/stores/assistants/AssistantOptionsStore.svelte.js";
    import {aiModelsStore} from "$lib/stores/AiModelsStore.svelte.js";
    import {aiToolsStore} from "$lib/stores/AiToolsStore.svelte.js";
    import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte.js";

    let { children } = $props();

    onMount(() => {
        assistantOptionsStore.load();
        aiModelsStore.load();
        aiToolsStore.load();
        assistantBuilderStore.init();
    });
</script>



<div class="wrapper-grid">
    <div class="content-col">
        {@render children()}
    </div>
</div>


<style>
    /* Fills the global AppLayout content area (not the viewport). Section
       navigation lives in the main sidebar (drill-down) and help now lives in
       the shared app-layout aside, so this only holds the page content. */
    .wrapper-grid{
        position: relative;
        width: 100%;
        height: 100%;
        /* Clip + constrain so this shell is a fixed viewport: the content column
           is the only scroll region. Without this, tall content also scrolls the
           parent AppContent, giving two stacked scrollbars. `minmax(0, 1fr)`
           gives the row a definite height so the inner `overflow-y: auto`
           actually kicks in. */
        min-height: 0;
        display: grid;
        box-sizing: border-box;
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr);
        overflow: hidden;
    }

</style>
