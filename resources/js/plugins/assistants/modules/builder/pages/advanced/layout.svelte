<script lang="ts">
    import { onMount } from "svelte";
    import { assistantOptionsStore } from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";
    // `aiModelStore`/`aiToolsStore` loading was already broken before this change
    // (unresolvable `$lib/stores/AiModelsStore.svelte.js` / `AiToolsStore.svelte.js`
    // imports — those stores are registered centrally in core.plugin.ts's store
    // registrar instead, see AiModelStore.loadData()/AiToolStore). Left as a TODO
    // rather than guessed at: out of scope for the builder-context conversion.
    import {createBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

    let { children } = $props();

    const { __ } = useTranslator();
    // This layout owns the builder session: it is created here, published to
    // the subtree (every `/advance/*` page and shared builder component picks
    // it up via useBuilderContext), and released when the layout unmounts.
    const builder = createBuilderContext(useToastContext(), __);

    onMount(() => {
        console.log('mount')
        assistantOptionsStore.load();
        // @todo wire up aiModelStore / aiToolStore loading here once the
        // intended lifecycle (registrar-driven vs. per-page) is confirmed.
        builder.init();
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
