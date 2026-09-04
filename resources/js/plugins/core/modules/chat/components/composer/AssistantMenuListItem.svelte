<!--
  @component A single assistant row inside `AssistantMenu` — mirrors `ToolMenuListItem`,
  but toggles an `@handle` in the composer message instead of a tool. The whole row acts as
  a checkbox (checked = the handle is currently present in the message); the visuals come
  from `AssistantRow`, shared with the caret-anchored `AssistantMentionPopup`.

  A separate info button on the right — or ArrowRight — opens `AssistantMenuDetail` for that
  assistant instead of toggling it, stopping propagation so it never also tags.

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
        /** The assistant (handle, label, grouping, pin state) this row represents. */
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

    // A row is lifted into the "Pinned" section by a local composer pin or by its
    // server-side flag; the pin button targets whichever mechanism owns the row's pin.
    const pinned = $derived(
        pinStore.isPinned('assistant', entry.assistant.id) || !!entry.assistant.pinned
    );

    function togglePin() {
        if (entry.assistant.onTogglePin) {
            entry.assistant.onTogglePin(!pinned);
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
    /* Mirrors `.tool-item-info`: a chevron that only opens the detail view. Rendered at
       full strength, with no hover state, so it reads as a plain affordance. */
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
        color: var(--color-text);
        cursor: pointer;
    }
</style>
