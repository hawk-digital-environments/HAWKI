<script lang="ts">
    import AssistantBrowser from "$lib/components/assistant/assistantBrowser/AssistantBrowser.svelte";
    import { assistantListStore } from "$lib/stores/assistants/AssistantListStore.svelte.js";
    import {__} from "$lib/utils/translator";

    // Load only the user's favourites (server-side `actions=favorite`).
    // setFilter resets to page 1 and fetches; loadList reports its own errors.
    // `includes` lists only extras — DEFAULT_INCLUDES are merged in for us.

    let searchQuery = $state("");
    let activeFilters = $state(new Set<string>());
    $effect(() => {
        assistantListStore.setFilter({
            name: searchQuery,
            assistant_category: [...activeFilters],
            is_favorite: false,
            release_stage: ['']
        });
    });
</script>

<div class="page-wrapper">
    <div class="page-content">

        <div class="page-header">
            <h2 class="page-title">{__('assistants.shared.title')}</h2>
            <p class="page-description">{__('assistants.shared.description')}</p>
        </div>

        <AssistantBrowser
                emptyTitle={__('assistants.shared.empty_title')}
                emptyDescription={__('assistants.shared.empty_description')}
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