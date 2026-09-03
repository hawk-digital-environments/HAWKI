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

  Pinned assistants are lifted into a "Pinned" section above the content sections: rows the
  user pinned locally (the `composer-pins` store, toggled per row by `MenuPinButton`) plus
  rows a hook flagged `pinned` (a favourited assistant). The remaining rows are grouped by
  their entry's `group` — the assistant's real category — with one fallback section for
  ungrouped rows.

  Each row's info icon (or ArrowRight) drills into `AssistantMenuDetail` in place of the
  list — a two-panel `DropdownMenuDetailView`, same as the tool picker.

  This is the browse-all entry point; typing `@` in the message instead opens
  `AssistantMentionPopup` at the caret, which filters the same assistants as you type.
-->
<script lang="ts">
    import {untrack} from 'svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import AssistantMenuListItem, {type AssistantMenuEntry} from '$plugins/core/modules/chat/components/composer/AssistantMenuListItem.svelte';
    import AssistantMenuDetail from '$plugins/core/modules/chat/components/composer/AssistantMenuDetail.svelte';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import MenuSearchField from '$plugins/core/modules/chat/components/composer/MenuSearchField.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import AtIcon from '$lib/components/ui/icons/iconset/AtIcon.svelte';
    import {defaultAssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance';
    import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');
    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();

    interface Props {
        /** Whether the tagging menu is open. Supports bind:open. */
        open?: boolean;
    }

    // Bindable so the menu can be opened externally, e.g. when the user types "@".
    let {open = $bindable(false)}: Props = $props();

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

    const needle = $derived(query.trim().toLowerCase());
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

    // A row is lifted into the "Pinned" section by a local composer pin or by the entry's
    // server-side `pinned` flag (a favourited assistant).
    function isLifted(entry: AssistantMenuEntry): boolean {
        return pinStore.isPinned('assistant', entry.assistant.id) || !!entry.assistant.pinned;
    }

    // Pinned rows: local pins in the order they were pinned, then favourite-pinned rows in
    // list order — and dropped from the sections below, so each assistant appears exactly once.
    const pinnedEntries = $derived([
        ...pinStore.partition('assistant', shownEntries, entry => entry.assistant.id).pinned,
        ...shownEntries.filter(entry => entry.assistant.pinned && !pinStore.isPinned('assistant', entry.assistant.id))
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
    <div transition:growTransition={{mode: 'horizontal'}}>
        <DropdownMenu
            disabled={composerContext.guard.disablesFeature('input', false)}
            bind:open
            contentProps={{class: 'assistant-menu-content'}}>
            {#snippet trigger({props})}
                <ButtonWithTooltip
                    variant="ghost"
                    iconLeft={AtIcon}
                    tooltip={__('chat.composer.assistantMenu.tagAssistant')}
                    highlight={props['data-state']}
                    {...props}/>
            {/snippet}

            {#if !detailEntry && searchEnabled}
                <MenuSearchField
                    bind:value={query}
                    placeholder={__('chat.composer.assistantMenu.searchPlaceholder')}/>
            {/if}

            <DropdownMenuDetailView open={!!detailEntry}>
                {#snippet details()}
                    {#if detailEntry}
                        <AssistantMenuDetail entry={detailEntry} onCloseDetail={closeAssistantDetail}/>
                    {/if}
                {/snippet}

                {#if pinnedEntries.length > 0}
                    <DropdownMenuLabel>{__('chat.composer.pin.pinnedLabel')}</DropdownMenuLabel>
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
