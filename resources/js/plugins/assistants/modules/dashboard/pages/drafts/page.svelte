<script lang="ts">
    import AssistantBrowser from "$plugins/assistants/components/assistantBrowser/AssistantBrowser.svelte";
    import {ReleaseMode} from "$plugins/assistants/types/assistant";
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {createAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte"

    const {__} = useTranslator();
    assistantOptionsStore.load();
    // This page owns the list: it is created here, published to the subtree
    // (AssistantBrowser picks it up via useAssistantListContext), and released
    // when the page unmounts.
    const list = createAssistantListContext(useApp(), useToastContext());

    let searchQuery = $state("");
    let activeFilters = $state(new Set<string>());

    $effect(() => {
        list.setFilter({
            name: searchQuery,
            assistant_category: [...activeFilters],
            release_stage: [
                ReleaseMode.DRAFT,
                ReleaseMode.PRIVATE
            ]
        });
    });
</script>
<div class="page-wrapper">
    <div class="page-content">

        <div class="page-header">
            <h2 class="page-title">{__('assistants.drafts.title')}</h2>
            <p class="page-description">{__('assistants.drafts.description')}</p>
        </div>

        <AssistantBrowser
                emptyTitle={__('assistants.drafts.empty_title')}
                emptyDescription={__('assistants.drafts.empty_description')}
                bind:searchQuery
                bind:activeFilters
        />
    </div>
</div>



<style>
    /* Store layout. The .page-* classes were styled only in the disabled
       _off/app.css; restored here (component-scoped) so the page has padding,
       scrolls vertically, and never overflows horizontally. */
    .page-wrapper {
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 80rem;
        width: 100%;
        margin: 0 auto;
        padding: var(--space-6);
    }

    .page-header {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .page-title {
        margin: 0;
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }

    .page-description {
        margin: 0;
        font-size: var(--font-size-base);
        color: var(--color-text-muted);
    }
</style>
