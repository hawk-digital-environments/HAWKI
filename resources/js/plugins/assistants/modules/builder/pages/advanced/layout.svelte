<script lang="ts">
    import { onMount } from "svelte";
    import { assistantOptionsStore } from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";
    // `aiModelStore`/`aiToolsStore` loading was already broken before this change
    // (unresolvable `$lib/stores/AiModelsStore.svelte.js` / `AiToolsStore.svelte.js`
    // imports — those stores are registered centrally in core.plugin.ts's store
    // registrar instead, see AiModelStore.loadData()/AiToolStore). Left as a TODO
    // rather than guessed at: out of scope for the builder-context conversion.
    import {createBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import ConfirmBuilderExit from "$plugins/assistants/modules/builder/components/ConfirmBuilderExit.svelte";

    let { children } = $props();

    const { __ } = useTranslator();
    // This layout owns the builder session: it is created here, published to
    // the subtree (every `/advance/*` page and shared builder component picks
    // it up via useBuilderContext), and released when the layout unmounts.
    const builder = createBuilderContext(useToastContext(), __);

    onMount(() => {
        assistantOptionsStore.load();
        builder.init();
    });
</script>



<div class="wrapper-grid">
    <div class="content-col">
        {@render children()}
    </div>
    <!-- Draft keep/discard decision when leaving the builder: registers its
         own router navigation guard and dialog for exactly as long as this
         layout (and therefore the builder session) is mounted. -->
    <ConfirmBuilderExit />
</div>


<style>
    /* Fills the global AppLayout content area (not the viewport). Section
       navigation lives in the main sidebar (drill-down) and help now lives in
       the shared app-layout aside, so this only holds the page content. */
    .wrapper-grid {
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

    /* Sole scroll region of the builder. `min-height: 0` lets it shrink inside
       the grid row so its own `overflow-y` engages instead of overflowing the
       shell (and the parent AppContent). */
    .content-col {
        height: 100%;
        min-height: 0;
        min-width: 0;
        overflow-y: auto;
    }

    /* Mobile: the floating nav toggle overlays the content top.
       Scroll-away padding keeps at-rest content below it while letting it
       scroll up under the SidebarContent fade overlay. */
    @media (--bp-md-and-smaller) {
        .content-col {
            padding-top: calc(var(--space-2_5) + var(--nav-row-h));
        }
    }

    /* Everything below styles the builder SUBTREE (section pages and shared
       builder components rendered inside `.wrapper-grid`), so the selectors
       are :global under the shell. Plain unlayered component styles — the
       app's default styling system; element defaults (button/label/hr, body
       text) live in the base layer, and the `.u-label`/`.u-text-muted`
       utilities cover class-based labels and muted text. The form controls
       themselves (input / textarea / select) are self-contained primitives
       (Input.svelte, Textarea.svelte, Select.svelte) with their own scoped
       styles. */

    /* Field layout -------------------------------------------------------- */
    /* Field container: label/error header stacked above the control;
       consumers use it directly (KnowledgeBases, ReleaseStage, …). */
    .wrapper-grid :global(.input-container) {
        position: relative;
        display: flex;
        gap: var(--space-2);
    }

    .wrapper-grid :global(.input-container.renderBlock) {
        flex-direction: column;
    }

    .wrapper-grid :global(.input-container.renderInline) {
        flex-direction: row;
        align-items: center;
    }

    .wrapper-grid :global(.field-header) {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: var(--space-2);
        flex-wrap: wrap;
    }

    /* Section page body --------------------------------------------------- */
    .wrapper-grid :global(.page-wrapper) {
        min-height: 100%;
    }

    .wrapper-grid :global(.page-content) {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 48rem;
        margin: 0 auto;
        padding: var(--space-8);
    }

    .wrapper-grid :global(.page-header) {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-2);
    }

    .wrapper-grid :global(.page-header .page-title) {
        margin: 0;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }

    .wrapper-grid :global(.page-header .page-description) {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .wrapper-grid :global(.grid-2) {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4);
    }
</style>
