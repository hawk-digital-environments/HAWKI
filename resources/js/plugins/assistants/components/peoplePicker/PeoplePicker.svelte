<!--
  @component Picks people by search: a search field whose matches can be
  added, and the picked people below, each removable. Owns no persistence —
  the caller holds the list and saves every change.
-->
<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import InformationCircleIcon from '$lib/components/ui/icons/iconset/InformationCircleIcon.svelte';
    import UserAdd01Icon from '$lib/components/ui/icons/iconset/UserAdd01Icon.svelte';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {
        SEARCH_LIMIT,
        SEARCH_MIN_LENGTH,
        searchUsers,
        type ShareUserCandidate
    } from '$plugins/assistants/api/resources/usersClient';
    import { debounce } from '$lib/utils/debounce';

    interface Props {
        /** The people picked so far. */
        people: ReadonlyArray<ShareUserCandidate>;
        /** Users never offered as matches (e.g. the creator). */
        excludeIds?: ReadonlyArray<number>;
        /** Shown in place of the list while nobody is picked. */
        emptyText: string;
        disabled?: boolean;
        onadd: (person: ShareUserCandidate) => void;
        onremove: (userId: number) => void;
    }

    const { people, excludeIds = [], emptyText, disabled = false, onadd, onremove }: Props = $props();

    const { __ } = useTranslator();
    const app = useApp();

    let query = $state('');
    let results = $state.raw<ShareUserCandidate[]>([]);
    let searching = $state(false);
    let searchError = $state(false);

    const searchInfo = $derived(
        __('assistants.share.search_info', { min: String(SEARCH_MIN_LENGTH), limit: String(SEARCH_LIMIT) })
    );
    const queryLongEnough = $derived(query.trim().length >= SEARCH_MIN_LENGTH);

    /** This query's matches, minus everyone already picked or excluded. */
    const matches = $derived.by(() => {
        const taken = new Set([...people.map((person) => person.userId), ...excludeIds]);
        return results.filter((user) => !taken.has(user.userId));
    });

    /** Guards against a slow earlier response clobbering a newer one. */
    let searchSeq = 0;

    const runSearch = debounce(async (value: string): Promise<void> => {
        const seq = ++searchSeq;
        const trimmed = value.trim();
        if (trimmed.length < SEARCH_MIN_LENGTH) {
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
        } catch (err) {
            if (seq !== searchSeq) return;
            results = [];
            searchError = true;
            console.error('People search failed:', err);
        } finally {
            if (seq === searchSeq) searching = false;
        }
    }, 250);

    $effect(() => {
        void runSearch(query);
    });

    /** Avatar URL via the storage proxy, or `undefined` for the initials fallback. */
    function avatarUrl(identifier: string | null): string | undefined {
        return app.uriBuilder.storageFileUri(identifier) ?? undefined;
    }
</script>

{#snippet personInfo(person: ShareUserCandidate)}
    <Avatar src={avatarUrl(person.avatar)} name={person.displayName} label={person.displayName} size={32} variant="neutral" />
    <span class="person-name">{person.displayName}</span>
{/snippet}

<div class="people-picker">
    <div class="search">
        <span class="search-icon"><Search01Icon size="1em" /></span>
        <Input
            bind:value={query}
            type="search"
            {disabled}
            placeholder={__('assistants.share.search_ph')}
            ariaLabel={__('assistants.share.search_ph')}
        />
        <Tooltip tooltip={searchInfo} side="bottom" sideOffset={4}>
            {#snippet children({ props })}
                <button {...props} type="button" class="icon-button search-info" aria-label={searchInfo}>
                    <InformationCircleIcon size="1em" />
                </button>
            {/snippet}
        </Tooltip>
    </div>

    {#if searching}
        <p class="hint" aria-live="polite">{__('assistants.share.searching')}</p>
    {:else if searchError}
        <p class="hint" aria-live="polite">{__('assistants.share.search_failed')}</p>
    {:else if queryLongEnough && matches.length === 0}
        <p class="hint" aria-live="polite">{__('assistants.share.no_results')}</p>
    {/if}

    {#if matches.length > 0}
        <ul class="list matches">
            {#each matches as person (person.userId)}
                <li>
                    <button type="button" class="person" {disabled} onclick={() => onadd(person)}>
                        {@render personInfo(person)}
                        <span class="person-action"><UserAdd01Icon size="1em" /></span>
                    </button>
                </li>
            {/each}
        </ul>
    {/if}

    {#if people.length > 0}
        <ul class="list picked">
            {#each people as person (person.userId)}
                <li class="person">
                    {@render personInfo(person)}
                    <button
                        type="button"
                        class="icon-button person-action"
                        {disabled}
                        aria-label={__('assistants.share.remove', { name: person.displayName })}
                        onclick={() => onremove(person.userId)}
                    >
                        <Cancel01Icon size="1em" />
                    </button>
                </li>
            {/each}
        </ul>
    {:else}
        <p class="hint">{emptyText}</p>
    {/if}
</div>

<style>
    .people-picker {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
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

    .hint {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .list {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .person {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        width: 100%;
        padding: var(--space-2);
        border: none;
        border-bottom: var(--divider);
        border-radius: var(--corner-sm);
        background: none;
        font: inherit;
        color: inherit;
        text-align: left;
    }

    li:last-child > .person,
    .person:last-child {
        border-bottom: none;
    }

    /* Matches are add buttons: the whole row is the target. */
    .matches .person {
        cursor: pointer;
    }

    .matches .person:hover:not(:disabled) {
        background: var(--color-active-surface);
    }

    .person:focus-visible,
    .icon-button:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }

    .person-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        font-weight: var(--font-weight-medium);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .person-action {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .icon-button {
        display: inline-flex;
        flex-shrink: 0;
        padding: 0;
        border: none;
        border-radius: var(--corner-sm);
        background: none;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .icon-button:hover:not(:disabled) {
        color: var(--color-text);
    }

    .search-info {
        color: inherit;
        cursor: help;
    }
</style>
