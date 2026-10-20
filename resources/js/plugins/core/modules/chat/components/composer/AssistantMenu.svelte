<!--
  @component Trigger button + popover for the composer's `@` tagging menu — the group-chat
  counterpart of `ToolMenu`, built from the same dropdown/checkbox-row pattern.

  Lists every taggable AI participant from the `ai-handle` store: in a room the existing
  HAWKI assistant (`@hawki`) first, then the assistants other plugins contribute via the
  `aiAssistants` hook (the assistants plugin appends the user's real assistants there,
  grouped by their category). In an AI conversation only those are listed — every message
  there already goes to HAWKI, so tagging it would say nothing.
  Each row toggles that assistant's `@handle` in `composerContext.message`, which is what
  makes a room message addressed to the AI (see `ComposerContext.containsAiHandle`).

  A message addresses at most one assistant: picking a row while another is active swaps it
  out (`ComposerContext.addHandleToMessage` replaces rather than appends), so the rows behave
  as one choice even though they are rendered as checkable items.

  Favourite assistants are lifted into a "Favorites" section above the content sections: rows
  a hook flagged `favorite` (the assistant's server-side favourite) plus rows favourited
  locally (the `composer-pins` store, for rows without a server-side favourite such as HAWKI;
  toggled per row by `MenuPinButton`). The remaining rows are grouped by
  their entry's `group` — the assistant's real category — with one fallback section for
  ungrouped rows.

  Each row's info icon (or ArrowRight) drills into `AssistantMenuDetail` in place of the
  list — a two-panel `DropdownMenuDetailView`, same as the tool picker.

  Typing `@` in the message opens this same menu in command mode (`command` set): the
  textarea keeps focus, what is typed after the `@` filters the list in place of the search
  field, and the textarea drives the highlight through `move`/`selectActive` — see
  `utils/commandMenu.ts`.
-->
<script lang="ts">
    import {untrack} from 'svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import AssistantMenuListItem, {type AssistantMenuEntry} from '$plugins/core/modules/chat/components/composer/AssistantMenuListItem.svelte';
    import AssistantMenuDetail from '$plugins/core/modules/chat/components/composer/AssistantMenuDetail.svelte';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import MenuSearchField from '$plugins/core/modules/chat/components/composer/MenuSearchField.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {chipPop} from '$lib/utils/transitions/chipPop';
    import Store01Icon from '$lib/components/ui/icons/iconset/Store01Icon.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import AtIcon from '$lib/components/ui/icons/iconset/AtIcon.svelte';
    import {defaultAssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance';
    import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {revealMenuRow} from '$plugins/core/modules/chat/components/composer/utils/revealMenuRow.js';
    import {createCommandMenu, createCommandKeeper, type MenuCommand} from '$plugins/core/modules/chat/components/composer/utils/commandMenu.js';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');
    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();
    const router = useRouter();

    interface Props {
        /** Whether the tagging menu is open. Supports bind:open. */
        open?: boolean;
        /** Set while a typed `@query` drives the menu from the message textarea. */
        command?: MenuCommand | null;
    }

    // Bindable so the menu can be opened externally, e.g. when the user types "@".
    let {open = $bindable(false), command = null}: Props = $props();

    // What the menu shows: the command, kept while the menu closes after it ended.
    const keepCommand = createCommandKeeper();
    const shownCommand = $derived(keepCommand(open, command));

    // When set, the picker shows the detail view for this assistant instead of the list.
    let detailAssistantId = $state<string | null>(null);

    // Free-text filter over the list panel; cleared whenever the menu closes.
    let query = $state('');

    // Whether this menu session offers the search field. Decided once, when the
    // menu opens, from the total number of assistants — a single-assistant menu
    // has nothing to filter. Never derived from the filtered result count and
    // never re-evaluated while open, so the field cannot appear or vanish
    // mid-interaction.
    let searchEnabled = $state(true);

    function toEntry(assistant: AiAssistant): AssistantMenuEntry {
        // Hook-provided assistants carry their own presentation (the assistants plugin
        // derives it from the assistant's name and avatar gradient); anything else
        // falls back to the name's first glyph on the brand colors.
        const appearance = assistant.appearance ?? defaultAssistantAppearance(assistant.label);
        return {
            assistant,
            emoji: appearance.icon,
            colors: appearance.colors,
            active: composerContext.handlesInMessage.includes(assistant.handle),
            onToggle(active) {
                if (active) {
                    composerContext.addHandleToMessage(assistant.handle);
                } else {
                    composerContext.removeHandleFromMessage(assistant.handle);
                }

                // Either way the choice is made, so the list has nothing left to offer — but
                // in the detail view the toggle is one control among several, and closing
                // would yank the panel out from under the user.
                if (!detailAssistantId) {
                    open = false;
                }
            }
        };
    }

    // `assistants` is HAWKI first, then the hook-contributed assistants; it is empty until
    // the `ai-handle` store has loaded.
    const entries = $derived(aiHandleStore.assistants.map(toEntry));
    // HAWKI is only taggable where it is one participant among many; in an AI conversation
    // it is the implicit recipient of every message.
    const hawkiEntry = $derived(composerContext.type === 'room'
        ? entries.find(entry => entry.assistant.id === 'hawki') ?? null
        : null);
    const otherEntries = $derived(entries.filter(entry => entry.assistant.id !== 'hawki'));

    // Rows are matched on what the user can see: the assistant's name and its @handle.
    function matchesQuery(entry: AssistantMenuEntry, needle: string): boolean {
        return [entry.assistant.label, entry.assistant.handle]
            .some(text => text.toLowerCase().includes(needle));
    }

    const needle = $derived((shownCommand ? shownCommand.query : query).trim().toLowerCase());
    const shownHawkiEntry = $derived(
        hawkiEntry && (!needle || matchesQuery(hawkiEntry, needle)) ? hawkiEntry : null
    );
    const shownOtherEntries = $derived(
        needle ? otherEntries.filter(entry => matchesQuery(entry, needle)) : otherEntries
    );
    const shownEntries = $derived([
        ...(shownHawkiEntry ? [shownHawkiEntry] : []),
        ...shownOtherEntries
    ]);

    // A row is lifted into the "Favorites" section by a local composer pin or by the entry's
    // server-side `favorite` flag.
    function isLifted(entry: AssistantMenuEntry): boolean {
        return pinStore.isPinned('assistant', entry.assistant.id) || !!entry.assistant.favorite;
    }

    // Favourite rows: local pins in the order they were set, then server-side favourites in
    // list order — and dropped from the sections below, so each assistant appears exactly once.
    const pinnedEntries = $derived([
        ...pinStore.partition('assistant', shownEntries, entry => entry.assistant.id).pinned,
        ...shownEntries.filter(entry => entry.assistant.favorite && !pinStore.isPinned('assistant', entry.assistant.id))
    ]);
    const unpinnedHawkiEntry = $derived(
        shownHawkiEntry && !isLifted(shownHawkiEntry) ? shownHawkiEntry : null
    );
    const unpinnedOtherEntries = $derived(shownOtherEntries.filter(entry => !isLifted(entry)));

    // The content sections: rows grouped by their entry's `group` (the assistant's real
    // category), in first-appearance order. Ungrouped rows share the fallback section.
    interface EntryGroup {
        id: string;
        label: string;
        entries: AssistantMenuEntry[];
    }

    const groups = $derived.by(() => {
        const ordered: EntryGroup[] = [];
        const byId = new Map<string, EntryGroup>();

        function groupOf(entry: AssistantMenuEntry): EntryGroup {
            const id = entry.assistant.group?.id ?? '_default';
            let group = byId.get(id);
            if (!group) {
                group = {
                    id,
                    label: entry.assistant.group?.label ?? __('chat.composer.assistantMenu.assistantsLabel'),
                    entries: []
                };
                byId.set(id, group);
                ordered.push(group);
            }
            return group;
        }

        if (unpinnedHawkiEntry) {
            groupOf(unpinnedHawkiEntry).entries.push(unpinnedHawkiEntry);
        }
        for (const entry of unpinnedOtherEntries) {
            groupOf(entry).entries.push(entry);
        }
        return ordered;
    });

    // The live entry shown in the detail view, rebuilt from `entries` so its `active`
    // state updates while the detail view is open.
    const detailEntry = $derived.by(() => {
        if (!detailAssistantId) {
            return null;
        }
        const entry = entries.find(e => e.assistant.id === detailAssistantId);
        return entry ? {...entry} : null;
    });

    // Shortcut next to the search field to the full assistant overview (the store), when a
    // plugin provides one.
    function openOverview(route: string) {
        open = false;
        void router.goToRoute(route);
    }

    function openAssistantDetail(entry: AssistantMenuEntry) {
        detailAssistantId = entry.assistant.id;
    }

    function closeAssistantDetail() {
        const handle = detailEntry?.assistant.handle;
        detailAssistantId = null;
        if (!handle) {
            return;
        }
        // Return focus to the row that opened the detail once the list re-renders.
        requestAnimationFrame(() => {
            document
                .querySelector<HTMLElement>(`.assistant-menu-content [data-assistant-handle="${handle}"]`)
                ?.focus();
        });
    }

    // The tagged assistant's row, unless it is filtered out.
    function activeRow(container: ParentNode): HTMLElement | null {
        const handle = composerContext.handlesInMessage[0];
        return handle && shownEntries.some(entry => entry.assistant.handle === handle)
            ? container.querySelector<HTMLElement>(`[data-assistant-handle="${CSS.escape(handle)}"]`)
            : null;
    }

    const commandMenu = createCommandMenu('.assistant-menu-content', () => shownCommand, activeRow);

    /** Moves the command highlight; called by `ComposerTextarea` on ArrowUp/ArrowDown. */
    export function move(delta: number): boolean {
        return commandMenu.move(delta);
    }

    /** Toggles the highlighted row; called by `ComposerTextarea` on Enter/Tab. */
    export function selectActive(): boolean {
        return commandMenu.selectActive();
    }

    // In command mode the highlight starts on the tagged assistant, else the first row, and
    // starts over whenever the typed query changes the rows. A frame later, so the content
    // has mounted and the filtered rows have rendered.
    // Keyed on the query string, not the command object, so only real filtering resets it.
    const commandQuery = $derived(shownCommand?.query ?? null);
    $effect(() => {
        if (!open || commandQuery === null) {
            return;
        }
        const frame = requestAnimationFrame(() => commandMenu.reset());
        return () => cancelAnimationFrame(frame);
    });

    // The list opens scrolled minimally to the tagged assistant, while focus stays in the
    // search field; ArrowDown then lands on that row. Matches the model picker. Two frames, so
    // the list has laid out after `MenuSearchField` mounts. Not in command mode, where the
    // command highlight takes over.
    $effect(() => {
        if (!open || untrack(() => shownCommand)) {
            return;
        }
        let frame = requestAnimationFrame(() => {
            frame = requestAnimationFrame(() => {
                const row = activeRow(document.querySelector('.assistant-menu-content') ?? document);
                if (!row) {
                    return;
                }
                revealMenuRow(row, '.assistant-menu-content');
            });
        });
        return () => cancelAnimationFrame(frame);
    });

    // Keyboard handling ahead of bits-ui's own (capture phase):
    // - Typing while a row has focus goes to the search field instead of bits-ui's
    //   typeahead, so the list still filters as you type.
    // - Arrowing out of the search field lands on the tagged assistant rather than the first
    //   row; falls through when that row is filtered out.
    function onContentKeydownCapture(event: KeyboardEvent) {
        const content = event.currentTarget as HTMLElement;
        const inSearch = event.target instanceof HTMLInputElement;
        if (!inSearch && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && event.key !== ' ') {
            const search = content.querySelector<HTMLInputElement>('.menu-search input');
            if (search) {
                // Focus moves before the key's default action, so the character lands in the field.
                event.stopPropagation();
                search.focus();
            }
            return;
        }
        if (event.key !== 'ArrowDown' || !inSearch) {
            return;
        }
        const row = activeRow(content);
        if (!row) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        row.focus();
    }

    // When closing the menu, the detail view is kept for a short delay to avoid flickering
    // mid-close; matches the tool picker.
    $effect(() => {
        if (!open) {
            query = '';
        }
    });

    // Snapshot the search decision per menu session: reading `entries`
    // untracked keeps this effect keyed on `open` only, so a list change
    // while the menu is open cannot flip the field on or off.
    $effect(() => {
        if (open) {
            searchEnabled = untrack(() => entries.length) > 1;
        }
    });

    $effect(() => {
        if (!open && detailAssistantId) {
            const t = setTimeout(() => {
                detailAssistantId = null;
            }, 200);
            return () => clearTimeout(t);
        }
    });
</script>

{#if hawkiEntry || otherEntries.length > 0}
    <div in:chipPop={{blur: 6}} out:chipPop={{direction: 'out', blur: 6}}>
        <DropdownMenu
            disabled={composerContext.guard.disablesFeature('input', false)}
            bind:open
            magneticHover={false}
            contentProps={{
                class: 'assistant-menu-content',
                onkeydowncapture: onContentKeydownCapture,
                onOpenAutoFocus: commandMenu.onOpenAutoFocus,
                onCloseAutoFocus: commandMenu.onCloseAutoFocus,
                onInteractOutside: commandMenu.onInteractOutside,
                onmousemove: commandMenu.onMouseMove,
                ...commandMenu.anchorProps()
            }}>
            {#snippet trigger({props})}
                <ButtonWithTooltip
                    variant="ghost"
                    iconLeft={AtIcon}
                    tooltip={__('chat.composer.assistantMenu.tagAssistant')}
                    highlight={props['data-state']}
                    {...props}/>
            {/snippet}

            {#if !detailEntry && searchEnabled && !shownCommand}
                <MenuSearchField
                    bind:value={query}
                    placeholder={__('chat.composer.assistantMenu.searchPlaceholder')}>
                    {#snippet action()}
                        {@const route = aiHandleStore.overviewRoute}
                        {#if route}
                            <ButtonWithTooltip
                                variant="ghost"
                                size="sm"
                                iconLeft={Store01Icon}
                                tooltip={__('chat.composer.assistantMenu.openStore')}
                                onclick={() => openOverview(route)}/>
                        {/if}
                    {/snippet}
                </MenuSearchField>
            {/if}

            <DropdownMenuDetailView open={!!detailEntry}>
                {#snippet details()}
                    {#if detailEntry}
                        <AssistantMenuDetail entry={detailEntry} onCloseDetail={closeAssistantDetail}/>
                    {/if}
                {/snippet}

                <!-- Inside the scrolling viewport, so the sliding hover scrolls with the rows. -->
                <MenuList class="dropdown-menu-list" bridgeSections>
                    {#if pinnedEntries.length > 0}
                        <DropdownMenuLabel>{__('chat.composer.favorite.label')}</DropdownMenuLabel>
                        {#each pinnedEntries as entry (entry.assistant.id)}
                            <AssistantMenuListItem {entry} onOpenDetail={openAssistantDetail}/>
                        {/each}
                    {/if}

                    {#each groups as group (group.id)}
                        {#if group.entries.length > 0}
                            <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                            {#each group.entries as entry (entry.assistant.id)}
                                <AssistantMenuListItem {entry} onOpenDetail={openAssistantDetail}/>
                            {/each}
                        {/if}
                    {/each}
                </MenuList>

                {#if !shownHawkiEntry && shownOtherEntries.length === 0}
                    <p class="no-results">{__('chat.composer.assistantMenu.noResults')}</p>
                {/if}
            </DropdownMenuDetailView>
        </DropdownMenu>
    </div>
{/if}

<style>
    .no-results {
        padding: var(--space-3);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        text-align: center;
    }

    /*
      Match the tool picker: one fixed width, and scrolling inside the animated detail-view
      viewport so the dropdown chrome stays anchored while the panels slide.
    */
    :global(.dropdown-content.dropdown-content--dropdown.assistant-menu-content) {
        width: calc(0.25rem * 76);
        /* Same cap as the model picker; the list scrolls inside the detail-view viewport. */
        max-height: min(24rem, calc(var(--bits-dropdown-menu-content-available-height, 999px) - var(--space-4)));
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
        display: flex;
        flex-direction: column;
        overflow: hidden;

        /* The padding collides with the inner view container (overflow: hidden), so the
           inner view handles its own padding instead. */
        padding: 0;

        :global(.view) {
            padding: var(--space-1)
        }
    }
</style>
