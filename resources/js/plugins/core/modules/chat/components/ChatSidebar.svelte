<!--
  @component Chat module's sidebar section: the scrollable conversation
  history, pinned chats above the recent ones, with pin/rename/delete per row.
  Both sections collapse from their title, and the choice is remembered in
  localStorage. The list edges fade only where more content exists in that
  direction. The module's "New Chat" action lives in the app sidebar's action
  area (see `NewChatButton.svelte`).
-->
<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import ChatHistoryItem from '$plugins/core/modules/chat/components/ChatHistoryItem.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {slide} from 'svelte/transition';
    import {cubicOut} from 'svelte/easing';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {mergeProps} from 'bits-ui';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import ChevronRightIcon from '$lib/components/ui/icons/iconset/ChevronRightIcon.svelte';
    import ListFilterIcon from '$lib/components/ui/icons/iconset/ListFilterIcon.svelte';
    import FilterMailRemoveIcon from '$lib/components/ui/icons/iconset/FilterMailRemoveIcon.svelte';
    import {HISTORY_BUCKETS, historyBucket, type HistoryBucket} from '$lib/utils/date.js';
    import type {ChatSummary} from '$plugins/core/modules/chat/types.js';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {}

    const {class: className, ...restProps}: Props = $props();

    type HistorySection = 'pinned' | 'recent';

    const COLLAPSED_STORAGE_KEY = 'hawki.chat.sidebar.collapsed';

    const app = useApp();
    const store = useStore('chat');
    const router = useRouter();
    const {__} = useTranslator();
    const toast = useToastContext();

    const RANGES = ['today', 'last7Days', 'last30Days'] as const satisfies readonly HistoryBucket[];

    let range = $state<(typeof RANGES)[number] | null>(null);
    let filterOpen = $state(false);

    const FilterIcon = $derived(range ? FilterMailRemoveIcon : ListFilterIcon);

    let collapsed = $state<Record<HistorySection, boolean>>({
        pinned: app.localStorage.getItem(`${COLLAPSED_STORAGE_KEY}.pinned`) === '1',
        recent: app.localStorage.getItem(`${COLLAPSED_STORAGE_KEY}.recent`) === '1'
    });

    function setCollapsed(section: HistorySection, value: boolean) {
        collapsed[section] = value;
        app.localStorage.setItem(`${COLLAPSED_STORAGE_KEY}.${section}`, value ? '1' : '0');
    }

    // Picking a range has to show its result, so it opens a collapsed section.
    function selectRange(option: (typeof RANGES)[number]) {
        range = option;
        setCollapsed('recent', false);
    }

    // Pinned chats in pin order. A pin can outlive its conversation (deleted on
    // another device), so a slug without a conversation is skipped.
    const pinned = $derived(store.pinnedSlugs.flatMap(slug => store.conversations.find(item => item.slug === slug) ?? []));
    const unpinned = $derived(store.conversations.filter(conversation => !store.isPinned(conversation.slug)));

    // The unpinned history limited to the selected range; the range does not
    // touch the pinned section. Ranges are cumulative ("last 7 days" includes
    // today), so a conversation matches when its bucket is no older than the
    // selected one. A conversation without any timestamp cannot be placed and
    // only shows while no range is selected.
    const conversations = $derived.by(() => {
        if (!range) return unpinned;
        const oldest = HISTORY_BUCKETS.indexOf(range);
        return unpinned.filter(conversation => {
            const bucket = historyBucket(conversation.updated_at ?? conversation.created_at ?? NaN);
            return HISTORY_BUCKETS.indexOf(bucket) <= oldest;
        });
    });

    // Fade the list edge only where there is actually more content in that
    // direction, so a short (or fully scrolled) list shows no fade at all.
    let historyEl = $state<HTMLElement | null>(null);
    let fadeTop = $state(false);
    let fadeBottom = $state(false);

    function updateFades() {
        const el = historyEl;
        if (!el) return;
        const max = el.scrollHeight - el.clientHeight;
        fadeTop = el.scrollTop > 1;
        fadeBottom = el.scrollTop < max - 1;
    }

    $effect(() => {
        const el = historyEl;
        if (!el) return;
        // An empty range swaps the list for a hint, so the observed children
        // change with the filtered count.
        void conversations.length;
        void pinned.length;
        // Re-measure when the list itself changes, not just on scroll.
        const observer = new ResizeObserver(updateFades);
        observer.observe(el);
        for (const child of el.children) observer.observe(child);
        updateFades();
        return () => observer.disconnect();
    });

    async function renameConversation(slug: string, name: string) {
        try {
            await store.rename(slug, name);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        }
    }

    async function removeConversation(slug: string) {
        try {
            await store.remove(slug);
            // Only leave the conversation that just disappeared; deleting some
            // other row from the list must not navigate away.
            if (router.isActive('chat.conversation', {params: {slug}})) {
                void router.goToRoute('chat.index');
            }
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        }
    }
</script>

{#snippet sectionTitle(section: HistorySection, title: string)}
    <h3 class="history-title">
        <button
            class="section-toggle"
            type="button"
            aria-expanded={!collapsed[section]}
            aria-controls="chat-history-{section}"
            onclick={() => setCollapsed(section, !collapsed[section])}
        >
            {title}
            <span class="section-chevron" class:open={!collapsed[section]} aria-hidden="true">
                <ChevronRightIcon size={14} strokeWidth={2}/>
            </span>
        </button>
    </h3>
{/snippet}

{#snippet historyRow(conversation: ChatSummary)}
    {@const generating = store.isGenerating(conversation.slug)}
    <!-- History rows are label-only: with every row carrying
         the same message icon it added no information. The
         leading slot is used solely to mark a conversation
         that is still generating. -->
    {#snippet generatingIndicator()}
        <span class="generation-indicator" aria-hidden="true"></span>
    {/snippet}
    <ChatHistoryItem
        media={generating ? generatingIndicator : undefined}
        name={conversation.name}
        href={{name: 'chat.conversation', params: {slug: conversation.slug}}}
        active={router.isActive('chat.conversation', {params: {slug: conversation.slug}})}
        pinned={store.isPinned(conversation.slug)}
        rowLabel={generating
            ? `${conversation.name}, ${__('chat.sidebar.generating')}`
            : conversation.name}
        onTogglePin={() => store.togglePin(conversation.slug)}
        onRename={name => renameConversation(conversation.slug, name)}
        onDelete={() => removeConversation(conversation.slug)}
    />
{/snippet}

<div {...restProps} class={["chat-sidebar", className]}>
    <div class="history-section">
        <!-- Pinned and recent chats share one scroller, so neither section can
             squeeze the other out of a short sidebar. -->
        <nav
            class="history"
            class:fade-top={fadeTop}
            class:fade-bottom={fadeBottom}
            bind:this={historyEl}
            onscroll={updateFades}
            aria-label={__('chat.sidebar.history')}
        >
            {#if store.listLoading && store.conversations.length === 0}
                <p class="hint">{__('chat.sidebar.loading')}</p>
            {:else if store.conversations.length === 0}
                <p class="hint">{__('chat.sidebar.empty')}</p>
            {:else}
                {#if pinned.length > 0}
                    <div class="history-group">
                        <div class="history-header">
                            {@render sectionTitle('pinned', __('chat.sidebar.pinned'))}
                        </div>
                        {#if !collapsed.pinned}
                            <div class="history-body" id="chat-history-pinned" transition:slide={{duration: 120, easing: cubicOut}}>
                                <SidebarItems>
                                    {#each pinned as conversation (conversation.slug)}
                                        {@render historyRow(conversation)}
                                    {/each}
                                </SidebarItems>
                            </div>
                        {/if}
                    </div>
                {/if}
                <!-- With every chat pinned there is nothing left to title or filter. -->
                {#if unpinned.length > 0}
                    <div class="history-group">
                        <div class="history-header">
                            {@render sectionTitle('recent', __(range ? `chat.sidebar.filter.${range}` : 'chat.sidebar.title'))}
                            <!-- While a range is selected the menu stays shut and the same
                                 button clears the range instead. The menu disables its
                                 trigger for that, which must not reach the native button. -->
                            <DropdownMenu bind:open={filterOpen} align="end" disabled={!!range}>
                                {#snippet trigger({props})}
                                    {@const label = __(range ? 'chat.sidebar.filter.clear' : 'chat.sidebar.filter.label')}
                                    <Tooltip tooltip={label} side="right">
                                        {#snippet children({props: tooltipProps})}
                                            <button
                                                {...mergeProps(props, tooltipProps, {disabled: false, onclick: () => range = null})}
                                                class="filter-trigger"
                                                class:open={filterOpen}
                                                class:filtered={!!range}
                                                type="button"
                                                aria-label={label}
                                            >
                                                <FilterIcon strokeWidth={2} aria-hidden="true"/>
                                            </button>
                                        {/snippet}
                                    </Tooltip>
                                {/snippet}
                                {#each RANGES as option (option)}
                                    <DropdownMenuItem onclick={() => selectRange(option)}>
                                        {__(`chat.sidebar.filter.${option}`)}
                                    </DropdownMenuItem>
                                {/each}
                            </DropdownMenu>
                        </div>
                        {#if !collapsed.recent}
                            <div class="history-body" id="chat-history-recent" transition:slide={{duration: 120, easing: cubicOut}}>
                                {#if conversations.length === 0}
                                    <p class="hint">{__('chat.sidebar.filter.empty')}</p>
                                {:else}
                                    <SidebarItems>
                                        {#each conversations as conversation (conversation.slug)}
                                            {@render historyRow(conversation)}
                                        {/each}
                                    </SidebarItems>
                                {/if}
                            </div>
                        {/if}
                    </div>
                {/if}
            {/if}
        </nav>
    </div>

</div>

<style>
    .chat-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
        gap: var(--nav-group-gap);
        /* The title and rows are text-only (see --nav-text-inset, SidebarRoot). */
        --history-text-inset: var(--nav-text-inset);
        /* The title reads at the same size as the search and the rows. */
        --history-title-size: var(--nav-font-size);
    }

    /* The search icon is 20px here and pulled out by 1px (AppSidebar). */
    @media (--bp-md-and-smaller) {
        .chat-sidebar {
            --history-title-size: var(--font-size-sm);
        }
    }

    .history-section {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }

    .history-group {
        display: flex;
        flex-direction: column;
    }

    /* Padding rather than a gap on the group, so the slide takes it along. */
    .history-body {
        padding-top: var(--space-1);
    }

    .history-group + .history-group {
        margin-top: var(--nav-group-gap);
    }

    .history-header {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: space-between;
        /* A full row, like every other line of the sidebar, so the title's text
           keeps the rows' rhythm and a section without a filter button is just as
           tall. */
        min-height: var(--nav-row-h);
        padding-left: var(--history-text-inset);
    }

    .history-title {
        margin: 0;
        font-size: var(--history-title-size);
        line-height: var(--line-height-normal);
        font-weight: var(--font-weight-normal);
        color: var(--color-nav-icon);
    }

    /* Hugs the text, so the chevron sits right next to the title. */
    .section-toggle {
        display: inline-flex;
        padding: 0;
        align-items: center;
        gap: var(--space-1);
        border: none;
        background: transparent;
        color: inherit;
        font: inherit;
        cursor: pointer;
        transition: color var(--duration-fast);
    }

    .section-toggle:hover {
        color: var(--color-text);
    }

    .section-chevron {
        display: inline-flex;
        flex-shrink: 0;
        transition: transform 120ms var(--easing-spring);
    }

    .section-chevron.open {
        transform: rotate(90deg);
    }

    /* Same colour and hover as the sidebar toggle (SidebarHeader); the icon takes
       the size of the profile settings icon (ProfileButton), 2px below the
       toggle's, and the box shrinks with it so the padding around it stays equal. */
    .filter-trigger {
        display: inline-flex;
        width: calc(var(--nav-row-h) - 2px);
        height: calc(var(--nav-row-h) - 2px);
        padding: 0;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-nav-icon);
        cursor: pointer;
        transition:
            color var(--duration-fast),
            background var(--duration-fast),
            opacity var(--duration-fast);
    }

    /* Revealed by hovering the title row on desktop. It stays while it is the only
       sign of an active filter. Touch has no hover, so it always shows there. */
    @media (--bp-bigger-than-md) {
        .filter-trigger {
            opacity: 0;
        }

        .history-header:hover .filter-trigger,
        .filter-trigger:focus-visible,
        .filter-trigger.open,
        .filter-trigger.filtered {
            opacity: 1;
        }
    }

    @media (hover: none) {
        .filter-trigger {
            opacity: 1;
        }
    }

    .filter-trigger:hover,
    .filter-trigger.open {
        background: var(--color-hover);
        color: var(--color-text);
    }

    .filter-trigger :global(svg) {
        width: var(--space-4);
        height: var(--space-4);
    }

    @media (--bp-md-and-smaller) {
        .filter-trigger :global(svg) {
            width: var(--nav-icon-size);
            height: var(--nav-icon-size);
        }
    }

    .history {
        min-height: 0;
        flex: 1;
        overflow-y: auto;
        /* The list still scrolls, but without a visible bar — same treatment
           as the other scrollable panels (see DropdownMenuDetailView). */
        scrollbar-width: none;
        -ms-overflow-style: none;
        /* Both edges are collapsed to zero by default; the scroll state opens
           the one that has content beyond it. */
        --fade-top: 0px;
        --fade-bottom: 0px;
        --history-fade: linear-gradient(
            to bottom,
            transparent 0,
            black var(--fade-top),
            black calc(100% - var(--fade-bottom)),
            transparent 100%
        );
        mask-image: var(--history-fade);
        -webkit-mask-image: var(--history-fade);
    }

    .history.fade-top {
        --fade-top: var(--space-6);
    }

    .history.fade-bottom {
        --fade-bottom: var(--space-6);
    }

    .history::-webkit-scrollbar {
        display: none;
    }

    /* Takes the place of a row, so it sits on the same rhythm as the rows it
       stands in for. */
    .hint {
        display: flex;
        align-items: center;
        min-height: var(--nav-row-h);
        margin: 0 var(--nav-item-pad-x) 0 var(--history-text-inset);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    .generation-indicator {
        height: 1rem;
        aspect-ratio: 1;
        border: 2px solid var(--color-surface);
        border-top-color: var(--color-interactive);
        border-right-color: var(--color-interactive);
        border-radius: 50%;
        animation: generation-spin 700ms linear infinite;
    }

    @keyframes generation-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .generation-indicator {
            border-color: var(--color-interactive);
            animation: none;
        }
    }
</style>
