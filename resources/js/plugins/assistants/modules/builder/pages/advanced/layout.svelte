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
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import {ReleaseMode} from "$plugins/assistants/types/assistant/ReleaseMode";
    import BuilderStepFooter from "$plugins/assistants/modules/builder/components/BuilderStepFooter.svelte";
    import ConfirmBuilderExit from "$plugins/assistants/modules/builder/components/ConfirmBuilderExit.svelte";
    import {breakpointsQueries} from '$lib/components/util/breakpoints/breakpoints.js';
    import BuilderTestPanel from "$plugins/assistants/modules/builder/components/BuilderTestPanel.svelte";

    let { children } = $props();

    const { __ } = useTranslator();
    const router = useRouter();
    // This layout owns the builder session: it is created here, published to
    // the subtree (every `/advance/*` page and shared builder component picks
    // it up via useBuilderContext), and released when the layout unmounts.
    const builder = createBuilderContext(useToastContext(), __);

    /**
     * Entering the builder on a published assistant must be acknowledged
     * first: the first saved change unpublishes it (see
     * UnpublishReleasedAssistantOnEdit) and it only goes live again after a
     * fresh review. The counterpart of the draft-exit confirmation — consent
     * on the way in, keep/discard on the way out.
     */
    /** The assistant the released-edit consent was decided for (shown or skipped). */
    let acknowledgedAssistantId = $state<string | null>(null);
    let releasedEditOpen = $state(false);

    // Entry consent: asked once per assistant, when the builder is entered
    // on a published one. Publishing from within the session flips the
    // stage — the id guard keeps that from re-asking — and a reload
    // continues a session rather than entering one, so it stays quiet too.
    $effect(() => {
        if (builder.loading) return;
        const {id, releaseStage} = builder.draft;
        if (!id || acknowledgedAssistantId === id) return;
        acknowledgedAssistantId = id;
        const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
        if (navigation?.type === 'reload') return;
        if (releaseStage === ReleaseMode.ORGANIZATIONAL
            || releaseStage === ReleaseMode.FEDERATED) {
            releasedEditOpen = true;
        }
    });

    /** Cancel: leave the builder for the assistant's detail page instead. */
    function leaveReleasedEdit(): void {
        releasedEditOpen = false;
        const id = builder.draft.id;
        if (id) {
            void router.goToRoute('assistants.dashboard.details', {id});
        } else {
            void router.goToRoute('assistants.dashboard.index');
        }
    }

    // The test chat floats over every step (docked as a column on wide
    // viewports). Open by default, except on small screens where it would
    // cover the whole builder — there it starts as the chat button.
    let testOpen = $state(!window.matchMedia(breakpointsQueries.bpMdAndSmaller).matches);

    onMount(() => {
        assistantOptionsStore.load();
        builder.init();
    });
</script>



<div class="wrapper-grid" class:test-open={testOpen}>
    <div class="content-col">
        {@render children()}
    </div>
    <BuilderStepFooter />
    <BuilderTestPanel bind:open={testOpen} />
    <!-- Draft keep/discard decision when leaving the builder: registers its
         own router navigation guard and dialog for exactly as long as this
         layout (and therefore the builder session) is mounted. -->
    <ConfirmBuilderExit />

    <!-- Consent before editing a published assistant: dismiss (ESC/backdrop)
         or "Edit anyway" stays in the builder; the cancel button leaves. -->
    <ConfirmDialog
            open={releasedEditOpen}
            onOpenChange={(open) => {
                releasedEditOpen = open;
            }}
            title={__('assistants.builder.edit_released_title')}
            description={__('assistants.builder.edit_released_description')}
            okLabel={__('assistants.builder.edit_released_confirm')}
            cancelLabel={__('assistants.builder.edit_released_cancel')}
            onConfirm={() => {
                releasedEditOpen = false;
            }}
            onCancel={leaveReleasedEdit}
    />
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
        grid-template-areas: 'main';
        overflow: hidden;
        --test-panel-w: 26rem;
        --test-fab-inset: var(--space-4);
    }

    /* While the test chat is collapsed its launcher sits in the bottom-right
       corner, on the step footer's row: the footer keeps its actions clear
       of it (launcher inset + 2.5rem launcher + gap). */
    .wrapper-grid:not(.test-open) {
        --step-footer-end-reserve: calc(var(--test-fab-inset) + 2.5rem + var(--space-3));
    }

    /* Wide viewports: the open test chat docks as a right-hand column. The
       track opens in step with the panel's morph (BuilderTestPanel), so the
       content makes room while the button grows into the column. */
    @media (--bp-xl) {
        .wrapper-grid {
            grid-template-columns: minmax(0, 1fr) 0rem;
            grid-template-areas: 'main .';
            transition: grid-template-columns 360ms cubic-bezier(0.3, 0, 0.2, 1) 30ms;
        }

        .wrapper-grid.test-open {
            grid-template-columns: minmax(0, 1fr) var(--test-panel-w);
            transition: grid-template-columns 480ms cubic-bezier(0.3, 0, 0.2, 1);
        }
    }

    @media (--bp-xl) and (prefers-reduced-motion: reduce) {
        .wrapper-grid, .wrapper-grid.test-open {
            transition: none;
        }
    }

    /* Sole scroll region of the builder. `min-height: 0` lets it shrink inside
       the grid row so its own `overflow-y` engages instead of overflowing the
       shell (and the parent AppContent). */
    .content-col {
        grid-area: main;
        height: 100%;
        box-sizing: border-box;
        /* Room to scroll the last content out from under the step footer. */
        padding-bottom: 8rem;
        min-height: 0;
        min-width: 0;
        overflow-y: auto;
        scrollbar-width: none;
    }

    .wrapper-grid {
        /* The section pages' content column width — one source of truth for
           the column (see :global(.page-content)). */
        --builder-content-w: 48rem;
    }

    .content-col :global(textarea) {
        scrollbar-width: none;
    }

    .content-col::-webkit-scrollbar,
    .content-col :global(textarea::-webkit-scrollbar) {
        display: none;
    }

    /* The step footer floats over the bottom of the content column. */
    .wrapper-grid > :global(.step-footer) {
        grid-area: main;
        align-self: end;
        z-index: 1;
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
        gap: var(--space-6);
        max-width: var(--builder-content-w);
        margin: 0 auto;
        padding: var(--space-8);
    }

    .wrapper-grid :global(.page-header) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
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
        gap: var(--space-6) var(--space-4);
    }
</style>
