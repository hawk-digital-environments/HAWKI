<!--
  @component A single assistant row inside `AssistantMenu` — mirrors `ToolMenuListItem`,
  but toggles an `@handle` in the composer message instead of a tool. The whole row acts as
  a checkbox (checked = the handle is currently present in the message); the visuals come
  from `AssistantRow`, shared wherever an assistant row is listed.

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
        /** Icon shown at the start of the row. */
        emoji: string;
        /** The assistant's two color stops, tinting its icon swatch and handle. */
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
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import MenuRowInfoButton from '$plugins/core/modules/chat/components/composer/MenuRowInfoButton.svelte';
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
    indicator="none"
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

        <MenuRowInfoButton label={__('chat.composer.assistantMenu.infoDefault')} onOpen={openDetail}/>
    {/snippet}
</DropdownMenuCheckboxItem>

<style>
    /* The whole row toggles the mention, so signal it as clickable. */
    :global(.dropdown-checkbox-item.assistant-menu-item) {
        cursor: pointer;
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

    /* The favourite and the info chevron only show on the row being pointed at or
       focused — through `--dropdown-item-action-opacity` and `MenuRowInfoButton`'s
       own reveal rules; tagged/pinned rows keep theirs visible, so their options
       are at hand. */
</style>
