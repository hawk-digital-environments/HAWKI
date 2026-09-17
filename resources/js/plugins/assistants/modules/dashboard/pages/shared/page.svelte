<script lang="ts">
    import AssistantBrowser from "$plugins/assistants/modules/dashboard/components/assistantBrowser/AssistantBrowser.svelte";
    import {readBrowserUrlState} from "$plugins/assistants/modules/dashboard/components/assistantBrowser/browserUrlState.js";
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {createAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte"
    import Page from '$lib/components/ui/page/Page.svelte';

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props (see core's ChatIndex.svelte), so this interface is intentionally
     * empty.
     */
    interface Props {
    }
    
    const {}: Props = $props();

    const {__} = useTranslator();
    assistantOptionsStore.load();
    // This page owns the list: it is created here, published to the subtree
    // (AssistantBrowser picks it up via useAssistantListContext), and released
    // when the page unmounts.
    const list = createAssistantListContext(useApp(), useToastContext());

    // Search text and category filters are event-driven: the search bar and
    // category bar report commits via callbacks, and each handler applies the
    // (page-specific) filter to the list. Seeding from the URL happens before
    // the single initial request below.
    const initial = readBrowserUrlState();
    let searchQuery = $state(initial.query);
    let activeFilters = $state(initial.categories);

    function applyFilter() {
        list.setFilter({
            name: searchQuery,
            assistant_category: [...activeFilters],
            shared_with_user: true
        });
    }

    function handleSearchChange(query: string) {
        searchQuery = query;
        applyFilter();
    }

    function handleFilterChange(filters: Set<string>) {
        activeFilters = filters;
        applyFilter();
    }

    applyFilter();
</script>

<Page title={__('assistants.shared.title')}>
    <div class="page-content">

        <AssistantBrowser
                searchQuery={searchQuery}
                activeFilters={activeFilters}
                onSearchChange={handleSearchChange}
                onFilterChange={handleFilterChange}
                emptyTitle={__('assistants.shared.empty_title')}
                emptyDescription={__('assistants.shared.empty_description')}
        />
    </div>
</Page>

<style>
    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 80rem;
        width: 100%;
        margin: 0 auto;
        padding: var(--space-6);
    }
</style>
