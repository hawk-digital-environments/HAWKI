<!--
  @component The favourite toggle shown on a row in `AssistantMenu` / `ToolMenu` / `ModelPicker`.

  A small heart button (filled when set) that favourites the row's assistant/tool via the
  `composer-pins` store, lifting it into the menu's "Favorites" section. Rows whose
  favourite lives on the server (a hook-provided assistant flagged `favorite`) instead
  pass the controlled `pinned`/`onToggle` props, routing the toggle to their own
  backend action; so do model rows, whose favourites live in the `model-favorites` store
  (`kind`/`id` are then not needed). Like the neighbouring info chevron it stops pointer/keyboard
  propagation so favouriting never also toggles the row it sits in.

  In the `row` variant (default) a non-favourite row only reveals the button on hover/focus —
  it reads `--dropdown-item-action-opacity` from the menu row it sits in, and host rows can
  opt it in further (see the `.menu-pin-button` styles they scope); a favourite row keeps it visible so
  the state is always readable. The `detail` variant is the same control for the
  `AssistantMenuDetail`/`ToolMenuDetail` header: always visible, sized to sit beside the
  panel's on/off toggle, and reachable by the detail panel's own arrow/Tab focus handling.

  ## Usage
  Rendered inside a row's `DropdownMenuCheckboxItem` (or the model picker's
  `DropdownMenuRadioItem`), next to the info button:
  ```svelte
  <MenuPinButton kind="tool" id={entry.tool.name}/>
  ```
  or in a detail panel's header, next to the toggle:
  ```svelte
  <MenuPinButton kind="tool" id={entry.tool.name} variant="detail"/>
  ```
-->
<script lang="ts">
    import FavouriteIcon from '$lib/components/ui/icons/iconset/FavouriteIcon.svelte';
    import type {ComposerPinKind} from '$plugins/core/stores/ComposerPinStore.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const pinStore = useStore('composer-pins');
    const {__} = useTranslator();

    interface Props {
        /** Which menu this row belongs to — pins are kept per kind. Required unless
         *  `pinned` and `onToggle` are both passed. */
        kind?: ComposerPinKind;
        /** The row's stable id: the assistant's id, or the tool's name. Required unless
         *  `pinned` and `onToggle` are both passed. */
        id?: string;
        /** `row` (default) is the compact icon shown on menu rows; `detail` is the
         *  labelled, always-visible variant for a detail panel's header. */
        variant?: 'row' | 'detail';
        /** Overrides the pin state read from the `composer-pins` store — for rows whose
         *  favourite lives on the server (a hook-provided assistant flagged `favorite`). */
        pinned?: boolean;
        /** Overrides the store toggle — routes the pin to the row's own backend action
         *  instead of the local pins. */
        onToggle?: () => void;
    }

    const {kind, id, variant = 'row', pinned: pinnedOverride, onToggle}: Props = $props();

    const pinned = $derived(pinnedOverride ?? (kind && id ? pinStore.isPinned(kind, id) : false));
    const label = $derived(pinned ? __('chat.composer.favorite.remove') : __('chat.composer.favorite.add'));

    // A touch larger in the detail header, where it sits next to the toggle rather than
    // inside a dense list row.
    const iconSize = $derived(variant === 'detail' ? 16 : 15);

    function togglePin(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        if (onToggle) {
            onToggle();
        } else if (kind && id) {
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
    aria-label={label}
    title={label}
    tabindex={variant === 'detail' ? 0 : -1}
    onpointerdown={(e) => e.stopPropagation()}
    onpointerup={(e) => e.stopPropagation()}
    onkeydown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.stopPropagation();
        }
    }}
    onclick={togglePin}>
    <FavouriteIcon size={iconSize}/>
</button>

<style>
    .menu-pin-button {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 2px;
        /* On a row the info chevron always follows, so pull it closer than the row's gap
           — the two read as one pair of controls. */
        margin: 0 calc(-1 * var(--space-1_5)) 0 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;

        /* Non-favourite rows keep the affordance out of the way until the row is hovered or
           focused, which the menu row reports through this property. */
        opacity: var(--dropdown-item-action-opacity, 0);
    }

    .menu-pin-button:hover {
        color: var(--color-text);
    }

    .menu-pin-button--pinned {
        opacity: 1;
        color: var(--color-text);
    }

    /* A set favourite fills its heart, as on the assistant store's favourite button. */
    .menu-pin-button--pinned :global(svg) {
        fill: currentColor;
    }

    /* In a detail panel there is no row to hover it out of, so the icon is always shown;
       it gets a hit area of its own next to the toggle, and no surface of its own — only
       the icon color changes on hover, as in a list row. */
    .menu-pin-button--detail {
        padding: var(--space-1, 0.25rem);
        margin: 0;
        opacity: 1;
    }
</style>
