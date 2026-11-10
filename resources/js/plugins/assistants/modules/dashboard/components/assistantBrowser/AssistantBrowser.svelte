<script lang="ts">
    import AssistantCard from "./AssistantCard.svelte";
    import PageNavigation from "./PageNavigation.svelte";
    import Searchbar from "$plugins/assistants/modules/dashboard/components/searchbar/Searchbar.svelte";
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import SearchRemoveIcon from '$lib/components/ui/icons/iconset/SearchRemoveIcon.svelte';
    import {StatusIcon} from '$lib/components/ui/icons';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import autoAnimate, {type AutoAnimationPlugin} from "@formkit/auto-animate";
    import {tick, untrack, type Snippet} from 'svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';
    import {morphFromDetail, morphToDetail} from "$plugins/assistants/utils/morphToDetail";
    import {useRouter} from "$lib/components/ui/routing/hooks/useRouter.svelte";
    import {useQueryState} from "$lib/components/ui/routing/hooks/useQueryState.svelte";
    import AssistantDetailModal from "$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetailModal.svelte";
    import CategoryBar from "$plugins/assistants/modules/dashboard/components/categoryBar/CategoryBar.svelte";

    const {__} = useTranslator();

    // Read the list published by the page that owns it. No props to thread
    // through, and nothing is retained once that page unmounts.
    const list = useAssistantListContext();

    let {
        searchQuery = "",
        activeFilters = new Set<string>(),
        onSearchChange,
        onFilterChange,
        hero,
        title,
        emptyTitle = __('assistants.browser.empty_title'),
        emptyDescription = __('assistants.browser.empty_description')
    } = $props<{
        /** Current committed query — rendered by the search bar and mirrored to `?q=`. */
        searchQuery?: string;
        /** Current category filter set — rendered by the category bar and mirrored to `?category=`. */
        activeFilters?: Set<string>;
        /** Called with the query to search for (debounced typing, Enter, icon click). */
        onSearchChange: (query: string) => void;
        /** Called with the next filter set whenever a category is toggled or reset. */
        onFilterChange: (filters: Set<string>) => void;
        /** Hero content (e.g. a page title) rendered above the search controls;
         *  turns the controls into a vertically centred hero section. */
        hero?: Snippet;
        /** Page title rendered as the h1 above the search controls. */
        title?: string;
        emptyTitle?: string;
        emptyDescription?: string;
    }>();

    const router = useRouter();

    // --- URL sync -------------------------------------------------------
    // Keep the URL's query string in sync with the live search/category/page
    // state, so filtered views are shareable/bookmarkable. The initial state
    // itself is seeded from the URL by the owning page (see
    // readBrowserUrlState) before the first request fires. Through the
    // router (not raw `history.replaceState`), so its own copy of the query
    // stays current and the detail modal's `?assistant=` survives; replaced,
    // not pushed, since the search box commits per debounced keystroke. The
    // page number is not restored from the URL: the list always starts on
    // page 1.
    $effect(() => {
        const changes = {
            q: searchQuery.trim() || null,
            category: activeFilters.size ? [...activeFilters].join(',') : null,
            page: list.currentPage > 1 ? String(list.currentPage) : null,
        };
        untrack(() => router.setQuery(changes, {replace: true}));
    });

    // --- Detail modal ---------------------------------------------------
    // An opened assistant lives in `?assistant=<id>` and shows as a
    // full-screen modal over this list, which stays mounted beneath — so
    // scroll position, filters and the loaded page survive the round trip.
    const detailId = useQueryState('assistant');
    /** Whether the open modal pushed its own history entry (a card click), so
     *  closing can step back over it instead of stacking another entry. */
    let pushedDetail = false;

    function openDetail(card: HTMLElement, id: string): void {
        morphToDetail(card, async () => {
            pushedDetail = true;
            router.setQuery({assistant: id}, {replace: false});
        });
    }

    /** Removes `?assistant=`; resolves once the modal is gone. */
    async function dismissDetail(): Promise<void> {
        if (pushedDetail) {
            pushedDetail = false;
            await new Promise<void>(resolve => {
                window.addEventListener('popstate', () => resolve(), {once: true});
                window.history.back();
            });
        } else {
            // Entered with `?assistant=` already in the URL (a shared link).
            detailId.current = null;
        }
        await tick();
    }

    /** The modal has no trigger element to return focus to (it opens from a
     * card, not a DialogTrigger), so closing would strand keyboard and screen
     * reader users on <body>. Hand focus back to the card's open button. */
    function returnFocusToCard(assistantId: string): void {
        document.querySelector<HTMLElement>(
            `[data-assistant-card="${CSS.escape(assistantId)}"] .open`,
        )?.focus();
    }

    function closeDetail(): void {
        const id = detailId.current;
        if (!id) {
            void dismissDetail();
            return;
        }
        morphFromDetail(id, async () => {
            await dismissDetail();
            returnFocusToCard(id);
        });
    }

    /** A deleted assistant has no card to morph back into: close plainly, refresh. */
    async function onDetailDeleted(): Promise<void> {
        await dismissDetail();
        await list.load();
    }

    // --- Grid motion ----------------------------------------------------
    // autoAnimate's defaults, except that a card which stays only slides to
    // its new spot. The default also tweens width/height when the card's row
    // height changes (cards stretch to their row), which reflows the card's
    // content mid-flight and makes it morph. Here the size snaps instead.
    const GRID_DURATION_MS = 250;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const gridMotion: AutoAnimationPlugin = (el, action, oldCoords, newCoords) => {
        // A plugin opts out of autoAnimate's own reduced-motion check.
        const duration = reducedMotion.matches ? 0 : GRID_DURATION_MS;
        if (action === 'add') {
            return new KeyframeEffect(el, [
                {transform: 'scale(.98)', opacity: 0},
                {transform: 'scale(.98)', opacity: 0, offset: 0.5},
                {transform: 'scale(1)', opacity: 1},
            ], {duration: duration * 1.5, easing: 'ease-in'});
        }
        if (action === 'remove') {
            return new KeyframeEffect(el, [
                {transform: 'scale(1)', opacity: 1},
                {transform: 'scale(.98)', opacity: 0},
            ], {duration, easing: 'ease-out'});
        }
        const dx = oldCoords && newCoords ? oldCoords.left - newCoords.left : 0;
        const dy = oldCoords && newCoords ? oldCoords.top - newCoords.top : 0;
        return new KeyframeEffect(el, [
            {transform: `translate(${dx}px, ${dy}px)`},
            {transform: 'translate(0, 0)'},
        ], {duration, easing: 'ease-in-out'});
    };
</script>

{#snippet controls()}
    <Searchbar defaultValue={searchQuery} onChange={onSearchChange} loading={list.loading} loadingLabel={__('assistants.browser.loading')} />
    <CategoryBar {activeFilters} {onFilterChange} />
{/snippet}

{#if hero}
    <section class="hero">
        {@render hero()}
        <div class="hero-controls">
            {@render controls()}
        </div>
    </section>
{:else}
    {#if title}
        <h1 class="browser-title">{title}</h1>
    {/if}
    {@render controls()}
{/if}

<div class="wrapper">
    <div class="grid-responsive" use:autoAnimate={gridMotion}>
        {#each list.assistants as assistant (assistant.id)}
            <AssistantCard assistant={assistant} onOpen={(card) => { if (assistant.id) openDetail(card, assistant.id); }} />
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

{#if list.assistants.length === 0}
    <!-- role=status (polite live region): the empty text appearing after a
         search/filter finishes is announced to screen readers. -->
    <div class="empty-list-msg" role="status">
        {#if list.loading}
            <StatusIcon icon={Search01Icon} tone="neutral" size="xl"/>
        {:else}
            <Tooltip
                    tooltip={__('assistants.browser.no_results')}
                    side="top"
                    delayDuration={300}
                    focusable={false}
                    hiddenLabel={__('assistants.browser.no_results')}
            >
                {#snippet children({props})}
                    <StatusIcon {...props} icon={SearchRemoveIcon} tone="neutral" size="xl"/>
                {/snippet}
            </Tooltip>
        {/if}
        <div class="text">
            {#if !list.loading}
                <span class="title">{emptyTitle}</span>
                <span class="description">{emptyDescription}</span>
            {/if}
        </div>
    </div>
{/if}



{#if detailId.current}
    <AssistantDetailModal
        id={detailId.current}
        initial={list.assistants.find(item => item.id === detailId.current)}
        onClose={closeDetail}
        onDeleted={onDetailDeleted}
    />
{/if}

<style>
    /* Responsive card grid. The full definition previously lived in the now
       disabled _off/containers.css; restored here (scoped to the browser) so the
       cards wrap into columns instead of overflowing. min(...,100%) keeps a
       single column from exceeding a narrow container. */
    /* Fills most of the viewport and centres the title and search in it, so
       the gallery starts just below — its top row peeks in as a scroll hint. */
    .hero {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-6);
        min-height: min(72dvh, 48rem);
        padding-bottom: var(--space-6);
    }
    .hero-controls {
        --categories-justify: safe center;
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        width: 100%;
        max-width: 42rem;
    }
    @media (--bp-bigger-than-sm) {
        .hero-controls {
            --categories-wrap: wrap;
        }
    }
    /* Same type as the page header bar's title it replaces. */
    .browser-title {
        margin: 0;
        font-size: var(--font-size-lg);
        line-height: var(--line-height-tight);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }
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
        /* Cards stretch to fill their track so wide screens don't leave large
           gaps around centered, max-width-capped cards. */
        justify-items: stretch;
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
