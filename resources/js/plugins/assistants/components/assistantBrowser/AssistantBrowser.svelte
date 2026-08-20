<script lang="ts">
    import AssistantCard from "./AssistantCard.svelte";
    import PageNavigation from "./PageNavigation.svelte";
    import Searchbar from "$plugins/assistants/components/searchbar/Searchbar.svelte";
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import autoAnimate from "@formkit/auto-animate";
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';
    import CategoryBar from "$lib/plugins/assistants/components/categoryBar/CategoryBar.svelte";

    const {__} = useTranslator();

    // Read the list published by the page that owns it. No props to thread
    // through, and nothing is retained once that page unmounts.
    const list = useAssistantListContext();

    let {
        searchQuery = $bindable(""),
        activeFilters = $bindable(new Set<string>()),
        emptyTitle = __('assistants.browser.empty_title'),
        emptyDescription = __('assistants.browser.empty_description')
    } = $props<{
        searchQuery?: string;
        activeFilters?: Set<string>;
        emptyTitle?: string;
        emptyDescription?: string;
    }>();

    // --- URL sync -------------------------------------------------------
    // Seed search/category from the query string on first load, so a shared,
    // bookmarked, or reloaded link reproduces the same list — the same idea
    // as a shopping site's filtered search URLs. This runs synchronously
    // during component setup (not inside an `$effect`), so `searchQuery`/
    // `activeFilters` already hold the right values by the time the owning
    // page's own `$effect` turns them into `list.setFilter(...)` for the
    // first time — no wasted "load everything, then reload filtered" request.
    const initialParams = new URLSearchParams(window.location.search);
    const initialQuery = initialParams.get('q');
    if (initialQuery) searchQuery = initialQuery;
    const initialCategory = initialParams.get('category');
    if (initialCategory) activeFilters = new Set(initialCategory.split(','));
    // The page number is intentionally not restored from the URL: `setFilter`
    // always resets `list` back to page 1, and that reset is driven by the
    // parent page's own `$effect` — racing a restore against it from here
    // would depend on cross-component effect ordering this isn't worth
    // depending on. The URL still reflects the current page once loaded
    // (below), so paginated links are shareable going forward.

    // Keep the URL's query string in sync with the live search/category/page
    // state. `replaceState`, not `pushState`: the search box has no debounce,
    // so a `pushState` per keystroke would spam browser history. The router
    // (`pathRoutingStrategy.svelte.ts`) only tracks `location.pathname`, so
    // rewriting just the query string here never interferes with it.
    $effect(() => {
        const params = new URLSearchParams();
        const trimmedQuery = searchQuery.trim();
        if (trimmedQuery) params.set('q', trimmedQuery);
        if (activeFilters.size) params.set('category', [...activeFilters].join(','));
        if (list.currentPage > 1) params.set('page', String(list.currentPage));

        const query = params.toString();
        const url = window.location.pathname + (query ? `?${query}` : '');
        if (url !== window.location.pathname + window.location.search) {
            window.history.replaceState(window.history.state, '', url);
        }
    });
    console.log(searchQuery)
</script>

<Searchbar bind:value={searchQuery} />
<CategoryBar bind:activeFilters />

<div class="wrapper">
    <div class="grid-responsive" use:autoAnimate>
        {#each list.assistants as assistant (assistant.id)}
            <AssistantCard assistant={assistant} />
        {/each}
    </div>

    <PageNavigation
        currentPage={list.currentPage}
        lastPage={list.lastPage}
        fromIndex={list.fromIndex}
        toIndex={list.toIndex}
        total={list.total}
        navigateTo={(page) => list.navigateTo(page)}
    />
</div>

{#if list.loading}
    <div class="status">{__('assistants.browser.loading')}</div>
{/if}

{#if !list.loading && list.assistants.length === 0}
    <div class="empty-list-msg">
        <div class="icon-wrapper">
            <span class="icon"><Search01Icon size="1em" /></span>
        </div>
        <div class="text">
            <span class="title">{emptyTitle}</span>
            <span class="description">{emptyDescription}</span>
        </div>
    </div>
{/if}



<style>
    /* Responsive card grid. The full definition previously lived in the now
       disabled _off/containers.css; restored here (scoped to the browser) so the
       cards wrap into columns instead of overflowing. min(...,100%) keeps a
       single column from exceeding a narrow container. */
    .wrapper {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    .grid-responsive {
        --element-size: 17rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(var(--element-size), 100%), 1fr));
        column-gap: var(--space-6);
        row-gap: var(--space-6);
        align-items: start;
        /* Cards stretch to fill their track so wide screens don't leave large
           gaps around centered, max-width-capped cards. */
        justify-items: stretch;
    }
    .status {
        padding: var(--space-4) 0;
        text-align: center;
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
    .empty-list-msg {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-8) 0;
        width: 100%;
        min-height: 20rem;
    }
    .empty-list-msg .icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 4rem;
        height: 4rem;
        border-radius: var(--corner-md);
        /* Same accent surface/ink pair as the active nav row and the selected
           filter chip, so the tile follows the theme in both modes. */
        background-color: var(--color-active-surface);
    }
    .empty-list-msg .icon {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-2xl);
        color: var(--color-active-text);
    }
    .empty-list-msg .text{
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: var(--space-1);
    }
    .empty-list-msg .text .title {
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .empty-list-msg .text .description {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
</style>
