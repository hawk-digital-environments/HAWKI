<!--
  @component The assistant share manager: pick users by search, grant edit
  rights per person, apply everything with one sync. Bottom sheet on mobile,
  centered dialog on desktop — same body. Creator-only; the caller gates the
  entry point.
-->
<script lang="ts">
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte.js';
    import { useConnection } from '$lib/app/hooks/useConnection.svelte.js';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import Copy01Icon from '$lib/components/ui/icons/iconset/Copy01Icon.svelte';
    import { ApiError } from '$plugins/assistants/api/errors';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import AssistantAvatarIcon from '$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte';
    import {
        listAssistantShares,
        syncAssistantShares
    } from '$plugins/assistants/api/resources/assistantsClient';
    import { searchUsers, type ShareUserCandidate } from '$plugins/assistants/api/resources/usersClient';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useRouter } from '$lib/components/ui/routing/index.js';
    import type { Assistant } from '$plugins/assistants/types/assistant';
    import { resolveAssistantAvatar } from '$plugins/assistants/utils/resolveAssistantAvatar';
    import {
        createShareSelection,
        type SharePerson
    } from '$plugins/assistants/modules/dashboard/components/shareDialog/shareSelection.svelte.js';
    import { debounce } from '$lib/utils/debounce';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
        /** The assistant whose shares are managed. */
        assistant: Assistant;
    }

    let { open = $bindable(false), assistant }: Props = $props();

    const { __ } = useTranslator();
    const toast = useToastContext();
    const breakpoint = useBreakpoint();
    const connection = useConnection();
    const app = useApp();
    const router = useRouter();

    const MIN_QUERY_LENGTH = 3;

    const selection = createShareSelection();

    /** The assistant's avatar, resolved exactly like the detail page. */
    const avatar = $derived(resolveAssistantAvatar(assistant.avatar, assistant.name ?? '?'));

    let loading = $state(false);
    let saving = $state(false);
    let query = $state('');
    let results = $state.raw<ShareUserCandidate[]>([]);
    let searching = $state(false);
    let searchError = $state(false);
    let searchInput = $state<HTMLInputElement | null>(null);

    // Every open starts fresh: reload the share list, drop stale picks and
    // search state from the previous session.
    $effect(() => {
        if (open && assistant.id) {
            query = '';
            results = [];
            searchError = false;
            void loadShares(assistant.id);
        }
    });

    async function loadShares(id: string): Promise<void> {
        loading = true;
        try {
            selection.adopt(await listAssistantShares(id));
        } catch (err) {
            toast.error(ApiError.from(err).userMessage);
            open = false;
        } finally {
            loading = false;
        }
    }

    // ─── Search ───────────────────────────────────────────────────────────

    /** Guards against a slow earlier response clobbering a newer one. */
    let searchSeq = 0;

    const runSearch = debounce(async (value: string): Promise<void> => {
        const seq = ++searchSeq;
        const trimmed = value.trim();
        if (trimmed.length < MIN_QUERY_LENGTH) {
            results = [];
            searching = false;
            searchError = false;
            return;
        }

        searching = true;
        searchError = false;
        try {
            const found = await searchUsers(trimmed);
            if (seq !== searchSeq) return;
            results = found;
            selection.seed(found);
        } catch (err) {
            if (seq !== searchSeq) return;
            results = [];
            searchError = true;
            console.error('Share user search failed:', err);
        } finally {
            if (seq === searchSeq) searching = false;
        }
    }, 250);

    $effect(() => {
        query;
        if (!open) return;
        void runSearch(query);
    });

    // ─── Actions ──────────────────────────────────────────────────────────

    const selfId = $derived(connection.hasUserInfo ? String(connection.userinfo.id) : null);
    const creatorId = $derived(assistant.creator ? String(assistant.creator.id) : null);

    function isExcluded(user: ShareUserCandidate): boolean {
        return String(user.userId) === selfId || String(user.userId) === creatorId;
    }

    /**
     * The list below the search, plain-multiselect style: selected people
     * (baseline shares and fresh picks), deselected baseline people pending
     * their removal, and this query's unpicked matches. Seeded-but-unpicked
     * candidates are not listed yet, so they must still pass through as
     * options (excluding all of `people` would eat every result).
     */
    const visiblePersons = $derived.by(() => {
        const baselineIds = new Set(selection.baseline.map((entry) => entry.userId));
        const kept = selection.people.filter(
            (person) => person.selected || baselineIds.has(person.userId)
        );
        const selected = kept.filter((person) => person.selected);
        const muted = kept.filter((person) => !person.selected);
        const keptIds = new Set(kept.map((person) => person.userId));

        const matches = results
            .filter((user) => !isExcluded(user) && !keptIds.has(user.userId))
            .map((user) => selection.find(user.userId))
            .filter((person): person is SharePerson => Boolean(person));

        return [...selected, ...muted, ...matches];
    });

    /** Avatar URL via the storage proxy, or `undefined` for the initials fallback. */
    function avatarUrl(identifier: string | null): string | undefined {
        return app.uriBuilder.storageFileUri(identifier) ?? undefined;
    }

    async function onSave(): Promise<void> {
        if (!assistant.id || saving) return;
        saving = true;
        try {
            selection.adopt(await syncAssistantShares(assistant.id, selection.payload));
            toast.success(__('assistants.detail.share_saved'));
            open = false;
        } catch (err) {
            toast.error(`${__('assistants.detail.share_save_failed')} ${ApiError.from(err).userMessage}`);
        } finally {
            saving = false;
        }
    }

    async function copyLink(): Promise<void> {
        const url = `${location.origin}${router.getPath('assistants.dashboard.store', {assistant: String(assistant.id)})}`;
        try {
            await navigator.clipboard.writeText(url);
            toast.success(__('assistants.detail.share_link_copied'));
        } catch {
            toast.error(__('assistants.detail.share_copy_failed'));
        }
    }
</script>

{#snippet avatarWithBadge()}
    <!-- The avatar itself is the copy-link action: one comfortable target,
         with the frosted chip as a corner hint (tooltip carries the label
         on hover, focus and long-press). -->
    <Tooltip tooltip={__('assistants.detail.share_copy_link')} side="bottom" sideOffset={4}>
        {#snippet children({props})}
            <button
                {...props}
                type="button"
                class="avatar-wrap"
                aria-label={__('assistants.detail.share_copy_link')}
                onclick={copyLink}
            >
                <AssistantAvatarIcon assistantAvatar={avatar} size="small" />
                <span class="copy-hint" aria-hidden="true">
                    <Copy01Icon size="1em" />
                </span>
            </button>
        {/snippet}
    </Tooltip>
{/snippet}

{#snippet personRow(person: SharePerson)}
    <!-- The name side is a real button: native focus/keyboard semantics, and
         the edit grant's switch is its sibling, not nested inside it. -->
    <li class="person" data-selected={person.selected || undefined}>
        <button
            type="button"
            class="person-toggle"
            aria-pressed={person.selected}
            onclick={() => selection.toggle(person.userId)}
        >
            <span class="person-info">
                <Avatar
                    src={avatarUrl(person.avatar)}
                    name={person.displayName}
                    label={person.displayName}
                    size={40}
                    variant="neutral"
                />
                <span class="person-names">
                    <span class="person-name">{person.displayName}</span>
                </span>
            </span>
        </button>
        {#if person.selected}
            <label class="person-edit">
                <span class="u-label">{__('assistants.detail.share_can_edit')}</span>
                <Switch bind:checked={person.canEdit} />
            </label>
        {/if}
    </li>
{/snippet}

{#snippet dialogBody()}
    {#if loading}
        <p class="hint" aria-live="polite">{__('assistants.browser.loading')}</p>
    {:else}
        <div class="share-body">
            <!-- Sheet variant: its title would sit in the drag-to-dismiss
                 region, where the copy button would fight the pointer
                 handlers — so the header (title left, avatar right) leads
                 the body instead, mirroring the desktop dialog's. -->
            {#if breakpoint.is('bpSmallerThanMd')}
                <div class="sheet-header">
                    <div class="sheet-header-text">
                        <p class="sheet-header-title">{__('assistants.detail.share_title')}</p>
                        <p class="sheet-header-description">{__('assistants.detail.share_description')}</p>
                    </div>
                    {@render avatarWithBadge()}
                </div>
            {/if}

            <div class="search">
                <span class="search-icon"><Search01Icon size="1em" /></span>
                <Input
                    bind:value={query}
                    bind:ref={searchInput}
                    type="search"
                    placeholder={__('assistants.detail.share_search_ph')}
                    ariaLabel={__('assistants.detail.share_search_ph')}
                />
            </div>

            {#if searching}
                <p class="hint" aria-live="polite">{__('assistants.detail.share_searching')}</p>
            {:else if searchError}
                <p class="hint" aria-live="polite">{__('assistants.detail.share_search_failed')}</p>
            {:else if query.trim().length >= MIN_QUERY_LENGTH && results.length === 0}
                <p class="hint" aria-live="polite">{__('assistants.detail.share_no_results')}</p>
            {/if}

            <ul class="results">
                {#each visiblePersons as person (person.userId)}
                    {@render personRow(person)}
                {/each}
            </ul>

            <div class="share-save">
                <Button
                    variant="fill"
                    disabled={!selection.dirty || saving}
                    onclick={onSave}
                >
                    {__('assistants.detail.share_save')}
                </Button>
            </div>
        </div>
    {/if}
{/snippet}

{#if breakpoint.is('bpSmallerThanMd')}
    <!-- No title prop: the visible header leads the body instead (the sheet
         falls back to an sr-only title, keeping the dialog labelled). -->
    <BottomSheet bind:open onOpenChange={(o) => (open = o)}>
        {@render dialogBody()}
    </BottomSheet>
{:else}
    <Dialog
        bind:open
        onOpenChange={(o) => (open = o)}
        title={__('assistants.detail.share_title')}
        description={__('assistants.detail.share_description')}
        bodyProps={{ class: 'share-dialog-body' }}
        contentProps={{
            onOpenAutoFocus: (event: Event) => {
                event.preventDefault();
                searchInput?.focus();
            }
        }}
    >
        {#snippet headerTrailing()}
            {@render avatarWithBadge()}
        {/snippet}
        {@render dialogBody()}
    </Dialog>
{/if}

<style>
    /* Section rhythm: one column, one gap — search / hint+results / save.
       Fills the dialog/sheet body so the results list can flex-shrink and
       become the internal scroller. */
    .share-body {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        gap: var(--space-3);
        min-height: 0;
    }

    /* Dialog's body is normally the scroll container; here it must not clip
       focus rings, so it opts out (see Dialog's bodyProps docs) and the
       results list scrolls itself. Doubled class to out-specify the shared
       `.dialog-body` rule regardless of stylesheet order. */
    :global(.dialog-body.share-dialog-body) {
        overflow: visible;
    }

    .hint {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .search {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--color-text-muted);
    }

    .search-icon {
        display: inline-flex;
        flex-shrink: 0;
    }

    /* Person rows: [toggle | edit switch]. The list is the dialog's internal
       scroller, flexed to whatever height the dialog/sheet grants it (search
       and save stay pinned); its padding keeps focus rings clear of the
       clipping edge. */
    .results {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        margin: 0;
        min-height: 0;
        padding: 2px;
        list-style: none;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    /* Every unselected row wears the same greyed-out look — pending removal
       or unpicked candidate alike; selection is the only visual axis. */
    .person {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3) var(--space-2);
        border-bottom: var(--divider);
        border-radius: var(--corner-sm);
        opacity: 0.6;
    }

    .person:hover {
        background: var(--color-active-surface);
    }

    /* Selected rows wear the repo's active scheme (MenuList/CommandResults);
       the edit grant on selected rows keeps them apart from a bare hover. */
    .person[data-selected] {
        background: var(--color-active-surface);
        opacity: 1;
    }

    .person[data-selected] .person-name {
        color: var(--color-active-text);
    }

    .person:last-child {
        border-bottom: none;
    }

    /* The selection toggle: the name side of the row, button semantics for
       free (focus, Enter/Space). Reset to look like plain row content. */
    .person-toggle {
        display: flex;
        flex: 1 1 auto;
        align-items: center;
        gap: var(--space-3);
        min-width: 0;
        padding: 0;
        border: none;
        background: none;
        font: inherit;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }

    .person-toggle:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }

    .person-info {
        display: flex;
        flex: 1;
        align-items: center;
        gap: var(--space-3);
        min-width: 0;
        text-align: left;
    }

    .person-names {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .person-name {
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .person-edit {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        flex-shrink: 0;
        margin-bottom: 0;
        white-space: nowrap;
        /* Breathing room from the row's right edge, so a hasty click lands
           on the grant or the toggle, not in between. */
        margin-inline-end: var(--space-4);
    }

    /* The avatar itself is the copy-link action: one comfortable target.
       The chip hints at it, frosted like the card favourite button. */
    .avatar-wrap {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        padding: 0;
        border: none;
        background: none;
        border-radius: var(--corner-md);
        cursor: pointer;
    }

    .avatar-wrap:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }

    .copy-hint {
        position: absolute;
        right: calc(var(--space-2) * -1);
        bottom: calc(var(--space-2) * -1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: var(--corner-full);
        background-color: oklch(100% 0 0 / 0.25);
        color: var(--color-text);
        font-size: var(--font-size-sm);
        backdrop-filter: blur(6px);
    }

    /* Sheet variant's header: title left, avatar right — mirrors the
       desktop dialog header. */
    .sheet-header {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: var(--space-4);
    }

    .sheet-header-text {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        flex: 1;
        min-width: 0;
    }

    .sheet-header-title {
        margin: 0;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium, 500);
    }

    .sheet-header-description {
        margin: 0;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    /* The save row is its own closing section, set off by a divider. */
    .share-save {
        display: flex;
        margin-top: var(--space-1);
        padding-top: var(--space-3);
        border-top: var(--divider);
    }

    .share-save :global(.btn) {
        flex: 1;
    }
</style>
