<script lang="ts">
    import AssistantBrowser from "$plugins/assistants/components/assistantBrowser/AssistantBrowser.svelte";
    import {ReleaseMode} from "$plugins/assistants/types/assistant";
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {createAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';

    const {__} = useTranslator();

    // This page owns the list: it is created here, published to the subtree
    // (AssistantBrowser picks it up via useAssistantListContext), and released
    // when the page unmounts.
    const list = createAssistantListContext(useApp(), useToastContext());

    let searchQuery = $state("");
    let activeFilters = $state(new Set<string>());
    console.log(activeFilters)
    console.log('searchQuery', searchQuery)
    // Every filter change re-runs the query from page 1. `setFilter` already
    // discards responses that arrive out of order, so fast typing is safe.


    list.setFilter({
        name: searchQuery,
        assistant_category: [...activeFilters],
        release_stage: [
            ReleaseMode.ORGANIZATIONAL,
            ReleaseMode.FEDERATED
        ]
    });
    // $effect(() => {
    //     console.log('effect')
    //     list.setFilter({
    //         name: searchQuery,
    //         assistant_category: [...activeFilters],
    //         release_stage: [
    //             ReleaseMode.ORGANIZATIONAL,
    //             ReleaseMode.FEDERATED
    //         ]
    //     });
    // });
</script>

<div class="page-wrapper">
    <div class="page-content">

        <div class="page-header">
            <h2 class="page-title">{__('assistants.store.title')}</h2>
            <p class="page-description">{__('assistants.store.description')}</p>
        </div>

        <AssistantBrowser
                emptyTitle={__('assistants.store.empty_title')}
                emptyDescription={__('assistants.store.empty_description')}
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
