<!--
  @component "Aktuelles" news feed as a modal. Lists every non-system,
  non-policy announcement addressed to the current user as a truncated preview
  (title, publish date and a plain-text excerpt); picking one drills down to
  the full announcement in place of the list. Escape or the back arrow return
  to the list. Mounted by `AppSidebar`, opened from the profile dropdown via
  `announcementsRequested`.

  In the detail, the dialog header swaps "News" for the back arrow and the
  announcement's title, with its publish date as the header description.

  The switch uses the same two-panel `DropdownMenuDetailView` as the composer's
  tool menu. A list row and the detail share their insets and text sizes, so
  the text stays put when switching.
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
        {dateStyle: 'medium'}
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
    description={detail?.starts_at ? dateDescription : undefined}
    contentProps={{class: 'announcements-dialog-content', onEscapeKeydown: handleEscape}}
    headerProps={{class: 'announcements-dialog-header'}}
>
    {#snippet title()}
        {#if detail}
            <ButtonWithTooltip
                variant="ghost"
                iconLeft={ArrowLeft01Icon}
                tooltip={__('ui.announcements.back')}
                bind:ref={backEl}
                onclick={closeDetail}
            />
            <span class="announcements-dialog-title">{announcementDisplayTitle(detail)}</span>
        {:else}
            {__('ui.announcements.pageTitle')}
        {/if}
    {/snippet}

    {#snippet dateDescription()}
        {#if detail?.starts_at}
            <time datetime={detail.starts_at}>{dateFormat.format(new Date(detail.starts_at))}</time>
        {/if}
    {/snippet}

    <DropdownMenuDetailView open={!!detail}>
        {#snippet details()}
            {#if detail}
                <div class="announcements-view">
                    <article class="announcement">
                        <div class="announcement__body">
                            <Markdown message={stripLeadingHeading(parseAnnouncementContent(detail.content).body)} headingBaseLevel={3}/>
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
        /* The end inset clears the absolutely positioned close button. */
        padding: var(--space-5) calc(var(--space-4) + 2rem) var(--space-4) var(--space-6);
        border-bottom: var(--divider);

        /* The back button (2rem, with the composer's hover background) is
           larger than its icon; let it overhang so the arrow lines up with
           the body text and the header keeps its height. */
        :global(.btn) {
            --btn-icon-size: 1rem;
            flex: none;
            margin: calc(-1 * var(--space-2));
        }

        /* The date (only shown in the detail) lines up with the title, past
           the back arrow and the title row's gap. */
        :global(.dialog-description) {
            padding-inline-start: calc(1rem + var(--space-2));
        }
    }

    .announcements-dialog-title {
        overflow: hidden;
        min-width: 0;
        text-overflow: ellipsis;
        white-space: nowrap;
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
       body below; the row's arrow trails. */
    .announcement {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 1rem;
        align-items: center;
        gap: var(--space-2) var(--space-3);
        padding: var(--space-3);
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
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

    /* The full text spans the row's arrow column too. */
    article.announcement .announcement__body {
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
