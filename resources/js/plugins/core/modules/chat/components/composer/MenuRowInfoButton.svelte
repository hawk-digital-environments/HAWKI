<!--
  @component The chevron button at the end of a picker row: the only target that opens
  the row's detail view, and the one control that must never toggle the row it sits in —
  it stops pointer and keyboard propagation so the row beneath stays untouched. Shared
  by the tool, assistant and model rows.

  Its reveal choreography lives here too, once for all three: in a dropdown the chevron
  stays out of sight until the row is pointed at or focused — checked/selected rows keep
  it shown — while the mobile sheet has no hover, so it stays there. Keyboard focus only
  counts when it came from the keyboard: a click leaves focus on the row, which would
  otherwise keep its controls shown after the pointer has moved on.

  @example
  <MenuRowInfoButton label={infoTooltip} onOpen={openDetail}/>
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';

    interface Props extends HTMLAttributes<HTMLButtonElement> {
        /** Screen-reader label: what opening the detail view shows. */
        label: string;
        /** Opens the detail view; receives the click event. */
        onOpen: (event: MouseEvent) => void;
    }

    const {label, onOpen, ...rest}: Props = $props();
</script>

<button
    type="button"
    class="menu-row-info"
    aria-label={label}
    tabindex={-1}
    onpointerdown={(e) => e.stopPropagation()}
    onpointerup={(e) => e.stopPropagation()}
    onkeydown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.stopPropagation();
        }
    }}
    onclick={onOpen}
    {...rest}>
    <ArrowRight01Icon size={16}/>
</button>

<style>
    .menu-row-info {
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

    .menu-row-info:hover {
        color: var(--color-text);
    }

    /* In the dropdown the chevron stays out of sight until the row is pointed at or
       focused; checked/selected rows keep it shown. */
    :global(.dropdown-content--dropdown :is(.dropdown-item, .dropdown-checkbox-item, .dropdown-radio-item)) .menu-row-info {
        opacity: 0;
    }

    /* The mobile sheet has no hover, so there the chevron stays; in the dropdown it
       only surfaces for the row being pointed at or keyboard-focused. */
    :global(.dropdown-content :is(.dropdown-item, .dropdown-checkbox-item, .dropdown-radio-item):is(:hover, :focus-visible, :has(:focus-visible), [data-state='checked'])) .menu-row-info {
        opacity: 1;
    }
</style>
