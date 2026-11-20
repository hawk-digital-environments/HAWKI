<script lang="ts">


    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator()

    let {
        currentPage,
        lastPage,
        fromIndex,
        toIndex,
        total,
        navigateTo
    } = $props<{
        currentPage: number;
        lastPage: number;
        fromIndex: number;
        toIndex: number;
        total: number;
        navigateTo: (page: number) => void;
    }>();

    type PageItem = { kind: 'page'; value: number } | { kind: 'ellipsis' };

    // Rule:
    //   lastPage <= 10                → show every page (no ellipsis)
    //   lastPage  > 10 and the
    //   `current..current+7` window
    //   already touches the last page → collapse the ellipsis, show the
    //                                   contiguous tail
    //   otherwise                     → current..current+7 + ellipsis + last
    const items = $derived.by<PageItem[]>(() => {
        if (lastPage <= 1) return [];

        const out: PageItem[] = [];
        if (lastPage <= 10) {
            for (let p = 1; p <= lastPage; p++) out.push({ kind: 'page', value: p });
            return out;
        }

        const windowEnd = currentPage + 7;
        if (windowEnd >= lastPage - 1) {
            for (let p = currentPage; p <= lastPage; p++) out.push({ kind: 'page', value: p });
            return out;
        }

        for (let p = currentPage; p <= windowEnd; p++) out.push({ kind: 'page', value: p });
        out.push({ kind: 'ellipsis' });
        out.push({ kind: 'page', value: lastPage });
        return out;
    });
</script>

{#if lastPage > 1}
    <div class="page-nav-bar">
        <div class="info">
            {fromIndex}–{toIndex} {__('assistants.browser.pagination_of')} {total}
        </div>
        <div class="pages-list">
            {#each items as item, i (i)}
                {#if item.kind === 'page'}
                    <button
                        class="page-btn"
                        class:active={item.value === currentPage}
                        aria-current={item.value === currentPage ? 'page' : undefined}
                        onclick={() => navigateTo(item.value)}
                    >
                        {item.value}
                    </button>
                {:else}
                    <span class="ellipsis" aria-hidden="true">…</span>
                {/if}
            {/each}
        </div>
    </div>
{/if}

<style>
    .page-nav-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        flex-wrap: wrap;
        padding: var(--space-4) 0;
    }
    .info {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .pages-list {
        display: flex;
        align-items: center;
        gap: var(--space-1);
    }
    .page-btn {
        min-width: 2.25rem;
        height: 2.25rem;
        padding: 0 var(--space-2);
        border: var(--border);
        border-radius: var(--corner-sm);
        background-color: transparent;
        color: var(--color-text);
        font-size: var(--font-size-sm);
        cursor: pointer;
        opacity: 0.9;
        transition:
            background-color var(--duration-fast),
            border-color var(--duration-fast),
            color var(--duration-fast),
            opacity var(--duration-fast);
    }
    .page-btn:hover {
        opacity: 1;
        background-color: var(--color-hover);
    }
    .page-btn.active {
        background-color: var(--color-accent-100);
        color: var(--color-accent-text);
        border-color: transparent;
        opacity: 1;
    }
    .ellipsis {
        min-width: 1.5rem;
        text-align: center;
        color: var(--color-text-muted);
        user-select: none;
    }
</style>
