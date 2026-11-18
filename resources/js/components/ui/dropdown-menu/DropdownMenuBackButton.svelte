<!--
  @component Back button for the detail view of a `DropdownMenuDetailView`: a muted
  chevron + label that returns to the list. Shared by the composer's tool, model and
  assistant detail panels.

  Pointer-down and click are kept from reaching the surrounding menu, so going back
  never also closes it or selects a row underneath. Bind `ref` to move focus onto it
  (e.g. when the detail opens and has no better primary control).

  ## Usage
  ```svelte
  <DropdownMenuBackButton
      bind:ref={backEl}
      label={__('chat.composer.toolMenu.backButton')}
      onBack={onCloseDetail}/>
  ```
-->
<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';

    interface Props extends Omit<HTMLButtonAttributes, 'children'> {
        /** The visible label, e.g. the name of the list it returns to. */
        label: string;
        /** Called when the button is activated. */
        onBack: () => void;
        /** The rendered button element. Bindable. */
        ref?: HTMLButtonElement | null;
    }

    let {label, onBack, ref = $bindable(null), class: className, ...rest}: Props = $props();
</script>

<button
    bind:this={ref}
    {...mergeProps(rest, {
        type: 'button',
        class: ['dropdown-menu-back', className],
        onpointerdowncapture: (e: PointerEvent) => e.stopPropagation(),
        onclick: (e: MouseEvent) => {
            e.preventDefault();
            e.stopPropagation();
            onBack();
        }
    })}>
    <ArrowLeft01Icon size={16}/>
    <span>{label}</span>
</button>

<style>
    .dropdown-menu-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5);
        /* A little more room after the label than before the chevron. */
        padding: var(--space-1) var(--space-2_5) var(--space-1) var(--space-2);
        border: none;
        border-radius: var(--corner-sm);
        background: none;
        color: var(--color-text-muted, var(--color-text));
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: background-color var(--duration-fast);
    }

    /* The chevron's ink is only the middle third of its 16px box: pull the empty
       sides in, so the gap to the label and the inset before it read as set. */
    .dropdown-menu-back :global(svg) {
        margin-inline: calc(var(--space-1) * -1);
    }

    .dropdown-menu-back:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }
</style>
