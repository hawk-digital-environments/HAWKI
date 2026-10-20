<!--
  @component A single assistant row inside `AssistantMenu` — mirrors `ToolMenuListItem`,
  but toggles an `@handle` in the composer message instead of a tool. The whole row acts as
  a checkbox (checked = the handle is currently present in the message); the visuals come
  from `AssistantRow`, shared with the caret-anchored `AssistantMentionPopup`.

  A separate info chevron on the right — revealed on the row being pointed at or focused, as in
  `ModelMenuListItem` — or ArrowRight opens `AssistantMenuDetail` for that assistant instead
  of toggling it, stopping propagation so it never also tags.

  ## Usage
  Rendered by `AssistantMenu`, once per assistant from the `ai-handle` store:
  ```svelte
  {#each studyEntries as entry (entry.assistant.id)}
      <AssistantMenuListItem {entry} onOpenDetail={openAssistantDetail}/>
  {/each}
  ```
-->
<script module lang="ts">
    import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import type {BeamColors} from '$lib/components/ui/border-beam/types.js';

    /** One assistant row, pre-wired with its appearance and toggle callback by `AssistantMenu`. */
    export interface AssistantMenuEntry {
        /** The assistant (handle, label, grouping, favourite state) this row represents. */
        assistant: AiAssistant;
        /** Emoji shown at the start of the row. */
        emoji: string;
        /** The assistant's two color stops, tinting its emoji swatch and handle. */
        colors: BeamColors;
        /** `true` when the assistant's handle is present in the current message. */
        active: boolean;
        /** Adds (`true`) or removes (`false`) the handle from the composer message. */
        onToggle: (active: boolean) => void;
    }
</script>
<script lang="ts">
    import DropdownMenuCheckboxItem from '$lib/components/ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
    import AssistantRow from '$plugins/core/modules/chat/components/composer/AssistantRow.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();

    interface Props {
        /** The assistant row to render. */
        entry: AssistantMenuEntry;
        /** Called with `entry` when the user clicks the info button or presses ArrowRight.
         *  `AssistantMenu` uses this to open `AssistantMenuDetail` for that assistant. */
        onOpenDetail?: (entry: AssistantMenuEntry) => void;
    }

    const {entry, onOpenDetail}: Props = $props();

    // A row is lifted into the "Favorites" section by a local composer pin (assistants
    // without a server-side favourite, e.g. HAWKI) or by its server-side favourite; the
    // favourite button targets whichever mechanism owns the row's state.
    const pinned = $derived(
        pinStore.isPinned('assistant', entry.assistant.id) || !!entry.assistant.favorite
    );

    function togglePin() {
        if (entry.assistant.onToggleFavorite) {
            entry.assistant.onToggleFavorite(!pinned);
        } else {
            pinStore.toggle('assistant', entry.assistant.id);
        }
    }

    function openDetail(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        onOpenDetail?.(entry);
    }

    function onRowKeydown(event: KeyboardEvent) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            event.stopPropagation();
            onOpenDetail?.(entry);
        }
    }
</script>

<!--
  bits-ui reads `closeOnSelect` *after* running the toggle, by which point `entry.active` has
  already flipped — deriving it from the row's state would invert the behaviour. The menu is
  closed explicitly by `AssistantMenu`'s toggle callback instead.
-->
<DropdownMenuCheckboxItem
    checked={entry.active}
    closeOnSelect={false}
    onCheckedChange={entry.onToggle}
    onkeydown={onRowKeydown}
    data-assistant-handle={entry.assistant.handle}
    aria-keyshortcuts="ArrowRight"
    class="assistant-menu-item">
    {#snippet children(checked)}
        <AssistantRow
            assistant={entry.assistant}
            emoji={entry.emoji}
            colors={entry.colors}
            checked={checked}/>

        <MenuPinButton
            kind="assistant"
            id={entry.assistant.id}
            {pinned}
            onToggle={togglePin}/>

        <button
            type="button"
            class="assistant-item-info"
            aria-label={__('chat.composer.assistantMenu.infoDefault')}
            tabindex={-1}
            onpointerdown={(e) => e.stopPropagation()}
            onpointerup={(e) => e.stopPropagation()}
            onkeydown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.stopPropagation();
                }
            }}
            onclick={openDetail}>
            <ArrowRight01Icon size={16}/>
        </button>
    {/snippet}
</DropdownMenuCheckboxItem>

<style>
    /* The whole row toggles the mention, so signal it as clickable. */
    :global(.dropdown-checkbox-item.assistant-menu-item) {
        cursor: pointer;
        /* Check now lives on the emoji swatch, so drop the reserved right padding. */
        padding-right: var(--space-2, calc(0.25rem * 2));
        /* Matches the vertical inset, so the swatch sits evenly in the row's corner. */
        padding-left: var(--space-1_5);
        gap: var(--space-2, calc(0.25rem * 2));
    }

    /* Concentric with the row's highlight: its corner radius minus the inset, so the gap
       around the swatch stays even into the corner too. */
    :global(.dropdown-checkbox-item.assistant-menu-item .assistant-row-emoji) {
        border-radius: calc(var(--corner-sm) - var(--space-1_5));
    }

    /* Two lines at the shared tight leading run a fraction past the 32px swatch and stretch
       the row; a 16px line each keeps the text exactly swatch-tall, so the swatch's inset
       above and below matches the one on the left. */
    :global(.dropdown-checkbox-item.assistant-menu-item :is(.assistant-row-label, .assistant-row-description)) {
        line-height: calc(0.25rem * 4);
    }

    /* Built-in absolute check is replaced by the one `AssistantRow` lays over its swatch. */
    :global(.dropdown-checkbox-item.assistant-menu-item .dropdown-item-indicator) {
        display: none;
    }

    /* The favourite only shows on the row the user is pointing at; favourite rows keep their own
       button visible. Mirrors `ToolMenuListItem`. */
    :global(.dropdown-checkbox-item.assistant-menu-item:hover .menu-pin-button),
    :global(.dropdown-checkbox-item.assistant-menu-item:focus-within .menu-pin-button) {
        opacity: 1;
    }

    /* In the dropdown the info chevron stays out of sight until the row is pointed at or
       focused, as in `ModelMenuListItem`; the mobile sheet has no hover, so it stays there. */
    :global(.dropdown-content--dropdown .dropdown-checkbox-item.assistant-menu-item .assistant-item-info) {
        opacity: 0;
    }

    :global(.dropdown-content .dropdown-checkbox-item.assistant-menu-item:is(:hover, :focus-within, [data-highlighted]) .assistant-item-info) {
        opacity: 1;
    }

    .assistant-item-info {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 0;
        margin: 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .assistant-item-info:hover {
        color: var(--color-text);
    }
</style>
