<script lang="ts">
    import AssistantBrowser from "$plugins/assistants/modules/dashboard/components/assistantBrowser/AssistantBrowser.svelte";
    import {ReleaseMode} from "$plugins/assistants/types/assistant";
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

    let searchQuery = $state("");
    let activeFilters = $state(new Set<string>());

    $effect(() => {
        list.setFilter({
            name: searchQuery,
            assistant_category: [...activeFilters],
            release_stage: [
                ReleaseMode.ORGANIZATIONAL,
                ReleaseMode.FEDERATED
            ]
        });
    });
</script>

<Page title={__('assistants.store.title')}>
    <div class="page-content">

        <AssistantBrowser
                emptyTitle={__('assistants.store.empty_title')}
                emptyDescription={__('assistants.store.empty_description')}
                bind:searchQuery
                bind:activeFilters
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
