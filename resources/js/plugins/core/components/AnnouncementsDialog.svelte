<!--
  @component "Aktuelles" news feed as a modal. Lists every non-system,
  non-policy announcement addressed to the current user as a truncated preview
  (title, publish date and a plain-text excerpt); picking one drills down to
  the full announcement in place of the list. Escape or the back arrow return
  to the list. Mounted by `AppSidebar`, opened from the profile dropdown via
  `announcementsRequested`.

  The switch uses the same two-panel `DropdownMenuDetailView` as the composer's
  tool menu. A list row and the detail share their insets, heading line and
  text sizes, so the date and text stay put when switching.
-->
<script lang="ts">
    import {tick} from 'svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {announcementDisplayTitle, announcementExcerpt, parseAnnouncementContent, stripLeadingHeading} from '$lib/app/components/announcements/announcementContent.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
    }

    let {open = $bindable(false)}: Props = $props();

    // Policies and system notices (e.g. upload conditions) are acknowledgement
    // flows, not news — they don't belong in the feed.
    const HIDDEN_TYPES = ['system', 'policy'];

    const app = useApp();
    const store = useStore('announcements');
    const {__} = useTranslator();

    const items = $derived(store.announcements.filter(announcement => !HIDDEN_TYPES.includes(announcement.type)));

    // When set, the dialog shows this announcement in full instead of the list.
    let detailId = $state<string | null>(null);
    const detail = $derived(detailId ? items.find(announcement => announcement.id === detailId) ?? null : null);

    let backEl = $state<HTMLButtonElement | null>(null);
    const rowEls: Record<string, HTMLButtonElement> = {};

    const dateFormat = $derived(new Intl.DateTimeFormat(
        app.localization.locale.lang.replace('_', '-'),
        {dateStyle: 'long'}
    ));

    async function openDetail(id: string): Promise<void> {
        detailId = id;
        await tick();
        backEl?.focus();
    }

    async function closeDetail(): Promise<void> {
        const id = detailId;
        detailId = null;
        await tick();
        // Return focus to the row that opened the detail.
        if (id) rowEls[id]?.focus();
    }

    // Escape backs out of the detail first; only on the list does it close the dialog.
    function handleEscape(event: KeyboardEvent): void {
        if (!detail) return;
        event.preventDefault();
        void closeDetail();
    }

    // Reset to the list once the close animation is done, so it doesn't flicker.
    $effect(() => {
        if (!open && detailId) {
            const t = setTimeout(() => detailId = null, 200);
            return () => clearTimeout(t);
        }
    });
</script>

<Dialog
    {open}
    onOpenChange={isOpen => open = isOpen}
    title={__('ui.announcements.pageTitle')}
    contentProps={{class: 'announcements-dialog-content', onEscapeKeydown: handleEscape}}
    headerProps={{class: 'announcements-dialog-header'}}
>
    <DropdownMenuDetailView open={!!detail}>
        {#snippet details()}
            {#if detail}
                <div class="announcements-view">
                    <article class="announcement announcement--detail">
                        <ButtonWithTooltip
                            variant="ghost"
                            iconLeft={ArrowLeft01Icon}
                            tooltip={__('ui.announcements.back')}
                            bind:ref={backEl}
                            onclick={closeDetail}
                        />
                        <header class="announcement__heading">
                            <h3 class="announcement__title">{announcementDisplayTitle(detail)}</h3>
                            {#if detail.starts_at}
                                <time datetime={detail.starts_at}>{dateFormat.format(new Date(detail.starts_at))}</time>
                            {/if}
                        </header>
                        <div class="announcement__body">
                            <Markdown message={stripLeadingHeading(parseAnnouncementContent(detail.content).body)} headingBaseLevel={4}/>
                        </div>
                    </article>
                </div>
            {/if}
        {/snippet}

        <div class="announcements-view">
            {#if items.length === 0}
                <p class="announcements-empty">{__('ui.announcements.empty')}</p>
            {:else}
                <ul class="announcements-list">
                    {#each items as announcement (announcement.id)}
                        {@const excerpt = announcementExcerpt(announcement.content)}
                        <li>
                            <button
                                type="button"
                                class="announcement announcement-row"
                                bind:this={rowEls[announcement.id]}
                                onclick={() => openDetail(announcement.id)}
                            >
                                <span class="announcement__heading">
                                    <span class="announcement__title announcement__title--truncate">{announcementDisplayTitle(announcement)}</span>
                                    {#if announcement.starts_at}
                                        <time datetime={announcement.starts_at}>{dateFormat.format(new Date(announcement.starts_at))}</time>
                                    {/if}
                                </span>
                                {#if excerpt}
                                    <span class="announcement__body announcement__body--excerpt">{excerpt}</span>
                                {/if}
                                <span class="announcement__trail"><ArrowRight01Icon size={16}/></span>
                            </button>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    </DropdownMenuDetailView>
</Dialog>

<style>
    /* Flex column so the detail view's viewport can shrink below its natural
       height and becomes the scroll region under the fixed header. */
    :global(.announcements-dialog-content.announcements-dialog-content) {
        display: flex;
        flex-direction: column;
        width: min(40rem, calc(100vw - 2 * var(--space-4)));
        max-width: 40rem;
        max-height: calc(100dvh - 2 * var(--space-4));
        overflow: hidden;
        padding: 0;
        gap: 0;
    }

    :global(.announcements-dialog-header.announcements-dialog-header) {
        flex: none;
        padding: var(--space-5) var(--space-6) var(--space-4);
        border-bottom: var(--divider);
    }

    .announcements-view {
        padding: var(--space-3);
    }

    .announcements-empty {
        padding: var(--space-3);
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    .announcements-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    /* Shared by a list row and the detail: heading line with the excerpt or
       body below; the row's arrow trails, the back arrow leads. */
    .announcement {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 1rem;
        align-items: center;
        gap: var(--space-2) var(--space-3);
        padding: var(--space-3);
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);

        /* The back button (2rem, with the composer's hover background) is
           larger than its 1rem slot; let it overhang on all sides so it
           neither widens the column nor makes the heading line taller. */
        > :global(.btn) {
            --btn-icon-size: 1rem;
            margin: calc(-1 * var(--space-2));
        }
    }

    .announcement--detail {
        grid-template-columns: 1rem minmax(0, 1fr);
        column-gap: var(--space-2);
    }

    .announcement-row {
        width: 100%;
        border: none;
        border-radius: var(--corner-md);
        background: none;
        color: var(--color-text);
        text-align: start;
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms);

        &:hover {
            background-color: var(--color-hover);
        }
    }

    .announcement__trail {
        display: flex;
        grid-area: 1 / 2 / span 2;
        color: var(--color-text-muted);
    }

    .announcement__heading {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        min-width: 0;

        > time {
            flex-shrink: 0;
            color: var(--color-text-muted);
            font-size: var(--font-size-xs);
            white-space: nowrap;
        }
    }

    .announcement__title {
        flex: 1;
        min-width: 0;
        font-size: inherit;
        font-weight: var(--font-weight-medium);
        line-height: inherit;
    }

    .announcement__title--truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .announcement__body {
        grid-column: 1;
        min-width: 0;
    }

    /* The full text starts at the leading edge, below the back button. */
    .announcement--detail .announcement__body {
        grid-column: 1 / -1;
    }

    .announcement__body--excerpt {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        color: var(--color-text-muted);
    }
</style>
