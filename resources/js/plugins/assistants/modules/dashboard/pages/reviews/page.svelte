<script lang="ts">
    import AssistantBrowser from "$plugins/assistants/modules/dashboard/components/assistantBrowser/AssistantBrowser.svelte";
    import {readBrowserUrlState} from "$plugins/assistants/modules/dashboard/components/assistantBrowser/browserUrlState.js";
    import CategoryButton from "$plugins/assistants/modules/dashboard/components/categoryBar/CategoryButton.svelte";
    import {ReviewStage} from "$plugins/assistants/types/assistant/ReviewStage";
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {createAssistantListContext} from "$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js";
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
    // when the page unmounts. The `assistant_review` include is privileged
    // (creator or org admin) and narrows the collection to the user's own
    // submissions — which is exactly this page's scope.
    const list = createAssistantListContext(useApp(), useToastContext(), ['assistant_review']);

    /** The "All" pseudo-state: every review status, mixed. */
    type ReviewFilter = ReviewStage | 'all';

    // The admin queue's tab order.
    const STATUS_KEYS = [
        ReviewStage.PENDING,
        ReviewStage.NEEDS_REVISION,
        ReviewStage.DENIED,
        ReviewStage.APPROVED
    ] as const;

    // Search is event-driven (the search bar reports commits via callback);
    // the status tabs are this page's filter interface in place of the
    // category bar. Seeding the query from the URL happens before the single
    // initial request below.
    const initial = readBrowserUrlState();
    let searchQuery = $state(initial.query);
    let status = $state<ReviewFilter>('all');

    function applyFilter() {
        list.setFilter({
            name: searchQuery,
            review_status: 'all' === status ? Object.values(ReviewStage) : [status]
        });
    }

    function handleSearchChange(query: string) {
        searchQuery = query;
        applyFilter();
    }

    function handleStatusChange(key: string) {
        status = key as ReviewFilter;
        applyFilter();
    }

    applyFilter();
</script>
<Page title={__('assistants.reviews.title')}>
    <div class="page-content">

        <AssistantBrowser
                searchQuery={searchQuery}
                onSearchChange={handleSearchChange}
                emptyTitle={__('assistants.reviews.empty_title')}
                emptyDescription={__('assistants.reviews.empty_description')}
        >
            {#snippet filters()}
                <!-- The category bar's pill row (CategoryButton), single-select
                     over the review states instead of categories. -->
                <div class="filter-bar" role="group" aria-label={__('assistants.reviews.title')}>
                    <div class="filter-list">
                        <CategoryButton
                                label={__('assistants.reviews.tab.all')}
                                isActive={status === 'all'}
                                onchange={() => handleStatusChange('all')}
                        />
                        {#each STATUS_KEYS as key (key)}
                            <CategoryButton
                                    label={__(`assistants.review.status.${key}`)}
                                    isActive={status === key}
                                    onchange={() => handleStatusChange(key)}
                            />
                        {/each}
                    </div>
                </div>
            {/snippet}
        </AssistantBrowser>
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

    /* The category bar's row recipe (see CategoryBar) around the shared pill
       buttons. */
    .filter-bar {
        display: grid;
        align-items: center;
    }

    .filter-list {
        display: flex;
        flex-direction: row;
        gap: var(--space-2);
        overflow-x: auto;
        padding: var(--space-0_5);
    }

    .filter-list::-webkit-scrollbar {
        display: none;
    }
</style>
