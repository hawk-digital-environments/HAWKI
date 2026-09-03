<!--
  @component The pin toggle shown on a row in `AssistantMenu` / `ToolMenu`.

  A small icon button that pins or unpins the row's assistant/tool via the
  `composer-pins` store, lifting it into the menu's "Pinned" section. Rows whose
  pin lives on the server (a hook-provided assistant flagged `pinned`) instead
  pass the controlled `pinned`/`onToggle` props, routing the toggle to their own
  backend action. Like the neighbouring info chevron it stops pointer/keyboard
  propagation so pinning never also toggles the row it sits in.

  In the `row` variant (default) an unpinned row only reveals the button on hover/focus
  (see the `.menu-pin-button` styles the host row scopes); a pinned row keeps it visible so
  the pin state is always readable. The `detail` variant is the same control for the
  `AssistantMenuDetail`/`ToolMenuDetail` header: always visible, sized to sit beside the
  panel's on/off toggle, and reachable by the detail panel's own arrow/Tab focus handling.

  ## Usage
  Rendered inside a row's `DropdownMenuCheckboxItem`, next to the info button:
  ```svelte
  <MenuPinButton kind="tool" id={entry.tool.name}/>
  ```
  or in a detail panel's header, next to the toggle:
  ```svelte
  <MenuPinButton kind="tool" id={entry.tool.name} variant="detail"/>
  ```
-->
<script lang="ts">
    import PinIcon from '$lib/components/ui/icons/iconset/PinIcon.svelte';
    import PinOffIcon from '$lib/components/ui/icons/iconset/PinOffIcon.svelte';
    import type {ComposerPinKind} from '$plugins/core/stores/ComposerPinStore.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();

    interface Props {
        /** Which menu this row belongs to — pins are kept per kind. */
        kind: ComposerPinKind;
        /** The row's stable id: the assistant's id, or the tool's name. */
        id: string;
        /** `row` (default) is the compact icon shown on menu rows; `detail` is the
         *  labelled, always-visible variant for a detail panel's header. */
        variant?: 'row' | 'detail';
        /** Overrides the pin state read from the `composer-pins` store — for rows whose
         *  pin lives on the server (a hook-provided assistant flagged `pinned`). */
        pinned?: boolean;
        /** Overrides the store toggle — routes the pin to the row's own backend action
         *  (e.g. toggling the assistant favourite) instead of the local pins. */
        onToggle?: () => void;
    }

    const {kind, id, variant = 'row', pinned: pinnedOverride, onToggle}: Props = $props();

    const pinned = $derived(pinnedOverride ?? pinStore.isPinned(kind, id));

    // A touch larger in the detail header, where it sits next to the toggle rather than
    // inside a dense list row.
    const iconSize = $derived(variant === 'detail' ? 16 : 15);

    function togglePin(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        if (onToggle) {
            onToggle();
        } else {
            pinStore.toggle(kind, id);
        }
    }
</script>

<button
    type="button"
    class="menu-pin-button"
    class:menu-pin-button--pinned={pinned}
    class:menu-pin-button--detail={variant === 'detail'}
    aria-pressed={pinned}
    aria-label={pinned ? __('chat.composer.pin.unpin') : __('chat.composer.pin.pin')}
    title={pinned ? __('chat.composer.pin.unpin') : __('chat.composer.pin.pin')}
    tabindex={variant === 'detail' ? 0 : -1}
    onpointerdown={(e) => e.stopPropagation()}
    onpointerup={(e) => e.stopPropagation()}
    onkeydown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.stopPropagation();
        }
    }}
    onclick={togglePin}>
    {#if pinned}
        <PinOffIcon size={iconSize}/>
    {:else}
        <PinIcon size={iconSize}/>
    {/if}
</button>

<style>
    .menu-pin-button {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 2px;
        margin: 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;

        /* Unpinned rows keep the affordance out of the way until the row is hovered or
           focused; the row styles opt it back in (`:hover`/`:focus-within`). */
        opacity: 0;
    }

    .menu-pin-button:hover {
        color: var(--color-text);
    }

    .menu-pin-button--pinned {
        opacity: 1;
        color: var(--color-text);
    }

    /* In a detail panel there is no row to hover it out of, so the icon is always shown;
       it gets a hit area of its own next to the toggle, and no surface of its own — only
       the icon color changes on hover, as in a list row. */
    .menu-pin-button--detail {
        padding: var(--space-1, 0.25rem);
        opacity: 1;
    }
</style>
