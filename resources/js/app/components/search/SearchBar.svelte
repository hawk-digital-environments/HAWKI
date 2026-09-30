<!--
  @component The reusable search surface: a text field, optional scope filters
  and the ranked result rows of one search session. Everything it shows comes
  from `app.search` — the kernel owns matching, ranking, the five-per-group /
  20-row caps, the frozen order and the recent selections; this component owns
  only the input, the filters and the announcements.

  A session lives exactly as long as the bar is `active`, so a palette that is
  closed holds no queries open and a second bar neither reloads sources nor
  cancels the first one's work. The field and its selection choreography
  belong to `CommandSearch`; this bar owns the session wiring, the filters
  and the announcements. Selecting a row hands the *live* entry to
  `onSelect`; the host closes itself first and then runs `entry.onSelect()`
  (see `SearchDialog`), which is why the action is never invoked here.

  Ordering freezes as soon as the user shows intent to pick something —
  actual pointer motion into the rows, or an arrow key — so a late server
  group appends at the bottom instead of moving the row under the cursor. Rows
  whose entity has been deleted or lost access stay in their slot as disabled
  placeholders until the next input change, again so nothing shifts.

  @example
  ```svelte
  <SearchBar
      search={app.search}
      active={open}
      showFilters
      onSelect={(entry) => { open = false; entry.onSelect(); }}
  />
  ```
-->
<script lang="ts">
    import {untrack, type Snippet} from 'svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import CommandSearch, {type CommandGroupDefinition} from '$lib/components/ui/command/CommandSearch.svelte';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {
        SearchApi,
        SearchEntry,
        SearchScope,
        SearchSession
    } from '$lib/kernel/search/types.js';

    interface Props {
        /** The kernel's search API, i.e. `app.search`. */
        search: SearchApi;
        /**
         * Whether the bar is visible and should hold a session. A closed
         * palette passes `false`: the session is disposed, its queries are
         * cancelled and the field starts blank the next time it opens.
         */
        active?: boolean;
        /**
         * Hard limit on what may be searched, e.g. `{moduleId: 'core:chat'}`.
         * The user's filters can only narrow this further, never widen it.
         */
        allowedScope?: SearchScope;
        /** Show the plugin/module filters above the results. */
        showFilters?: boolean;
        /**
         * Fired with the live entry behind the chosen row. The host is
         * expected to close itself *before* invoking `entry.onSelect()`.
         */
        onSelect: (entry: SearchEntry) => void;
        /** Accessible name of the field and its result list. */
        label?: string;
        /** Focus the search input when opening a palette. */
        autofocus?: boolean;
        /** Rendered at the trailing edge of the field, e.g. an Esc keycap. */
        hint?: Snippet;
    }

    let {
        search,
        active = true,
        allowedScope,
        showFilters = false,
        onSelect,
        label,
        autofocus = false,
        hint
    }: Props = $props();

    const {__} = useTranslator();

    const uid = $props.id();
    const hintId = `${uid}-hint`;

    const fieldLabel = $derived(label ?? __('ui.search.title'));

    /** Sentinel for the filters' "all" entry; `''` reads as "nothing picked". */
    const ALL = '*';

    let query = $state('');
    let pluginFilter = $state(ALL);
    let moduleFilter = $state(ALL);

    // ── Session ──────────────────────────────────────────────────────────
    // One session per visible bar. `allowedScope` is usually written inline
    // by the host, so the effect keys off its *contents*: a fresh object with
    // the same ids must not tear down a working session.

    let session = $state<SearchSession | null>(null);
    const scopeKey = $derived(`${allowedScope?.pluginId ?? ''}|${allowedScope?.moduleId ?? ''}`);

    $effect(() => {
        if (!active) return;
        void scopeKey;
        const currentSearch = search;
        const current = untrack(() => currentSearch.createSession({allowedScope}));
        session = current;
        return () => {
            current.dispose();
            session = null;
        };
    });

    // Each opening starts from a blank field and the host's own scope; a stale
    // query would hide most of the list before the user has typed anything.
    $effect(() => {
        if (active) return;
        query = '';
        pluginFilter = ALL;
        moduleFilter = ALL;
    });

    // ── Scope ────────────────────────────────────────────────────────────

    const allPlugins = $derived(search.scopeOptions.plugins);
    const allModules = $derived(search.scopeOptions.modules);

    /** Plugins the host allows. A pinned module pins its plugin as well. */
    const pluginOptions = $derived.by(() => {
        const pinned = allowedScope?.moduleId
            ? allModules.find(module => module.id === allowedScope?.moduleId)?.pluginId
            : allowedScope?.pluginId;
        return pinned ? allPlugins.filter(plugin => plugin.id === pinned) : allPlugins;
    });

    /** Modules the host allows, narrowed by the plugin the user picked. */
    const moduleOptions = $derived.by(() => {
        const allowed = allowedScope?.moduleId
            ? allModules.filter(module => module.id === allowedScope?.moduleId)
            : allowedScope?.pluginId
              ? allModules.filter(module => module.pluginId === allowedScope?.pluginId)
              : allModules;
        return pluginFilter === ALL ? allowed : allowed.filter(module => module.pluginId === pluginFilter);
    });

    // A filter with a single choice cannot narrow anything, so it is left out
    // rather than offered as a control that does nothing.
    const filtersVisible = $derived(showFilters && (pluginOptions.length > 1 || moduleOptions.length > 1));

    /**
     * What the session is asked to search: the host's limit first, then the
     * user's picks. Choosing a module implies its plugin, and the two combine
     * with AND.
     */
    const scope = $derived.by((): SearchScope | undefined => {
        const moduleId = allowedScope?.moduleId ?? (moduleFilter === ALL ? undefined : moduleFilter);
        const impliedPluginId = moduleId
            ? allModules.find(module => module.id === moduleId)?.pluginId
            : undefined;
        const pluginId =
            allowedScope?.pluginId ?? impliedPluginId ?? (pluginFilter === ALL ? undefined : pluginFilter);
        if (!pluginId && !moduleId) return undefined;
        return {pluginId, moduleId};
    });

    function choosePlugin(value: string) {
        pluginFilter = value;
        // A module of another plugin can no longer be part of the scope.
        const owner = allModules.find(module => module.id === moduleFilter)?.pluginId;
        if (value !== ALL && owner && owner !== value) moduleFilter = ALL;
    }

    function chooseModule(value: string) {
        moduleFilter = value;
        const owner = allModules.find(module => module.id === value)?.pluginId;
        if (owner) pluginFilter = owner;
    }

    const pluginItems = $derived([
        {value: ALL, label: __('ui.search.filters.allPlugins')},
        ...pluginOptions.map(plugin => ({value: plugin.id, label: plugin.label}))
    ]);

    const moduleItems = $derived([
        {value: ALL, label: __('ui.search.filters.allModules')},
        ...moduleOptions.map(module => ({value: module.id, label: module.label}))
    ]);

    // The triggers show the current value, so their names have to as well.
    const pluginFilterName = $derived(
        __('ui.search.filters.pluginValue', {
            value: pluginItems.find(item => item.value === pluginFilter)?.label ?? ''
        })
    );
    const moduleFilterName = $derived(
        __('ui.search.filters.moduleValue', {
            value: moduleItems.find(item => item.value === moduleFilter)?.label ?? ''
        })
    );

    // ── Input ────────────────────────────────────────────────────────────

    // Query dispatch is suspended for the length of an IME composition, so a
    // half-composed syllable never reaches the providers. The command field
    // reports the composition and keeps its keys quiet meanwhile.
    function handleComposition(composing: boolean) {
        if (composing) {
            session?.suspend();
            return;
        }
        session?.setInput({query, scope});
        session?.suspend(false);
    }

    $effect(() => {
        const current = session;
        if (!current) return;
        const input = {query, scope};
        untrack(() => current.setInput(input));
    });

    // ── Results ──────────────────────────────────────────────────────────

    const sessionState = $derived(session?.state);
    const groups = $derived(sessionState?.groups ?? []);
    const providerErrors = $derived(sessionState?.providerErrors ?? []);
    const pending = $derived(Boolean(sessionState?.localPending || sessionState?.remotePending));
    const pendingLabel = $derived(
        sessionState?.localPending && !sessionState.remotePending ? __('ui.search.searchingLocal')
            : sessionState?.remotePending && !sessionState.localPending ? __('ui.search.searchingRemote')
            : __('ui.search.searching')
    );
    const rowCount = $derived(groups.reduce((total, group) => total + group.items.filter(row => row.available).length, 0));

    /**
     * The command value of one row: its group plus its entity, joined on a
     * NUL. The same entity may sit in several groups at once (its own and
     * "Recently used"); Command highlights every item whose value equals the
     * current one, so rows sharing a bare entity key would all light up and
     * share an `aria-activedescendant` target. Scoping by group keeps the
     * value row-unique; {@link entityKeyOf} splits it back apart.
     */
    function commandValue(groupId: string, entityKey: string): string {
        return `${groupId}\u0000${entityKey}`;
    }

    /** The entity key behind a {@link commandValue}; group ids cannot contain the NUL separator. */
    function entityKeyOf(value: string): string {
        return value.slice(value.indexOf('\u0000') + 1);
    }

    /** Every selectable row's value; the command field keeps its highlight inside this set. */
    const selectableValues = $derived(
        new Set(groups.flatMap(group => group.items.filter(row => row.available).map(row => commandValue(group.id, row.entityKey))))
    );

    /** Whether the kernel froze the row order; a frozen order keeps the user's highlight. */
    const frozen = $derived(sessionState?.frozen ?? false);

    const commandGroups = $derived<CommandGroupDefinition[]>(
        groups.map(group => ({
            id: group.id,
            label: group.label,
            items: group.items.map(row => ({
                value: commandValue(group.id, row.entityKey),
                label: row.title,
                // A row whose entity is gone keeps its slot so the list does
                // not shift, and says why it can no longer be picked.
                description: row.available ? row.description : __('ui.search.unavailable'),
                icon: row.icon,
                disabled: !row.available
            }))
        }))
    );

    const groupLabels = $derived(new Map(groups.map(group => [group.id, group.label])));

    // ── Selection ────────────────────────────────────────────────────────
    // The command field owns the highlight and its gestures (see
    // CommandSearch); this bar only validates the picked row against the
    // live session.

    function choose(value: string) {
        // The kernel re-checks the row and records the static history; a row
        // that has gone stale in the meantime yields nothing and does nothing.
        const entry = session?.select(entityKeyOf(value));
        if (!entry) return;
        onSelect(entry);
    }

    // ── Announcements ────────────────────────────────────────────────────
    // The spinner speaks for the search while the list is still empty; from
    // the first row on, the status line is the only thing that talks. The
    // empty state is announced once, by its visible paragraph below.

    const showSpinner = $derived(pending && rowCount === 0);

    const status = $derived.by(() => {
        if (!active || showSpinner) return '';
        if (pending) return pendingLabel;
        if (providerErrors.length) return '';
        if (rowCount === 0) return '';
        if (rowCount === 1) return __('ui.search.resultCountOne');
        return __('ui.search.resultCount', {count: String(rowCount)});
    });
</script>

<div class="search-bar">
    {#if filtersVisible}
        <div class="search-filters" role="group" aria-label={__('ui.search.filters.title')}>
            {#if pluginOptions.length > 1}
                <SingleSelect
                    items={pluginItems}
                    value={pluginFilter}
                    onValueChange={choosePlugin}
                    triggerProps={{'aria-label': pluginFilterName}}
                />
            {/if}
            {#if moduleOptions.length > 1}
                <SingleSelect
                    items={moduleItems}
                    value={moduleFilter}
                    onValueChange={chooseModule}
                    triggerProps={{'aria-label': moduleFilterName}}
                />
            {/if}
        </div>
    {/if}

    <CommandSearch
        class="search-command"
        label={fieldLabel}
        placeholder={__('ui.search.placeholder')}
        bind:value={query}
        {autofocus}
        groups={commandGroups}
        selectableValues={selectableValues}
        {frozen}
        resetKey={scopeKey}
        onSelect={choose}
        onIntent={() => session?.freezeOrder()}
        onCompositionChange={handleComposition}
        describedBy={hintId}
        resultsLabel={__('ui.search.resultsLabel')}
        iconLeft={Search01Icon}
        {hint}
    />

    <p id={hintId} class="u-sr-only">{__('ui.search.keyboardHint')}</p>

    {#if active}
        {#if showSpinner}
            <Loader active label={pendingLabel}>
                {#snippet children()}{/snippet}
            </Loader>
        {:else if rowCount === 0 && providerErrors.length === 0}
            <!-- A live region of its own, so the empty state is announced
                 exactly once — the status line above stays silent for it. -->
            <p class="search-empty" role="status">
                {query ? __('ui.search.noResults', {query}) : __('ui.search.empty')}
            </p>
        {/if}

        <!-- One region per concern: progress and counts here, failures below.
             Neither re-reads the list. Both stay mounted so a late message is
             announced instead of arriving with a freshly inserted region. -->
        <span class="u-sr-only" role="status" aria-atomic="true">{status}</span>

        {#if pending && rowCount > 0}
            <!-- Visible counterpart of the announcement above: results are
                 already selectable while a group is still on its way. -->
            <p class="search-pending">{pendingLabel}</p>
        {/if}
    {/if}
    {#if active}
        <div role="alert">
            {#if providerErrors.length > 0}
                <ul class="search-error-list">
                    {#each providerErrors as error (error.providerId)}
                        {@const group = groupLabels.get(error.groupId) ?? __('ui.search.resultsLabel')}
                        <li class="search-error">
                            <span class="search-error-text">
                                {__('ui.search.errorInGroup', {group})}
                            </span>
                            <Button
                                variant="ghost"
                                size="xs"
                                aria-label={__('ui.search.retryLabel', {group})}
                                onclick={() => session?.retry(error.providerId)}
                            >
                                {__('ui.search.retry')}
                            </Button>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    {/if}
</div>

<style>
    /* Command renders these elements itself, so they are addressed globally
       under the bar's own class. Size and placement are the host's business
       (`.search-dialog .search-bar`); everything inside is decided here. */
    :global(.search-bar) {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    :global(.search-command) {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    /* ── Filters ──────────────────────────────────────────────────────── */

    :global(.search-bar .search-filters) {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        border-bottom: var(--border);
    }

    /* ── Results, status, errors ──────────────────────────────────────── */

    :global(.search-bar .command-list) {
        padding: var(--space-1);
    }

    /* Command moves `data-selected` on hover as well, so one rule paints both
       the keyboard and the pointer highlight. */
    :global(.search-bar .command-item[data-selected]) {
        background-color: var(--color-hover);
    }

    :global(.search-bar .search-empty) {
        margin: 0;
        padding: var(--space-6) var(--space-4);
        text-align: center;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    /* Below the rows, so a group that is still on its way never pushes the
       results the user is already reading. */
    :global(.search-bar .search-pending) {
        margin: 0;
        padding: var(--space-1) var(--space-4) var(--space-2);
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        line-height: var(--line-height-normal);
    }

    :global(.search-bar .search-error-list) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        margin: 0;
        padding: var(--space-2) var(--space-4);
        list-style: none;
        border-top: var(--border);
    }

    :global(.search-bar .search-error) {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        justify-content: space-between;
        font-size: var(--font-size-xs);
        color: var(--color-text);
    }

    :global(.search-bar .search-error-text) {
        min-width: 0;
    }

    :global(.search-bar .search-error-detail) {
        display: block;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
    }
</style>
