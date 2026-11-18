<!--
  @component Selectable row used inside a `RadioRowGroup`.

  Renders its `children` alongside a radio indicator and reflects the group's
  current selection. Clicking selects it; can be disabled per-row or via the
  group. Space/Enter select the focused row; the group handles the arrow keys.

  `trailing` sits at the row's end edge (status dots, an info button). It is a
  sibling of the radio rather than part of it, so its controls stay their own
  focusable, clickable elements and never select the row.

  The row has no fill of its own: it registers with the group's `MenuList` (as
  the active row when checked), so the list's sliding highlights mark the
  selection and the pointer.

  Must be used inside a `RadioRowGroup` — it reads shared state (current
  value, group `disabled`, `name`, Tab stop) via `getRadioRowContext()` and
  throws if no group is found in the component tree above it.

  @example
  ```svelte
  <RadioRowGroup value={selection} onChange={handleChange}>
      <RadioRow value="auto">Auto</RadioRow>
      <RadioRow value="native" disabled={!hasNativeSupport}>
          Native
          {#snippet trailing()}<InfoPopover info={nativeInfo}/>{/snippet}
      </RadioRow>
  </RadioRowGroup>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {Spring} from 'svelte/motion';
    import {getRadioRowContext} from '$lib/components/ui/radio-row/RadioRowContext.svelte.js';
    import MenuListItem from '$lib/components/ui/menu-list/MenuListItem.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this row represents within the group. */
        value: string;
        /** Disable this row. The group's disabled state also applies. */
        disabled?: boolean;
        /** Content pinned to the row's end edge, outside the radio itself. */
        trailing?: Snippet;
    }

    const {
        children: content,
        disabled: givenDisabled,
        value,
        trailing,
        class: className,
        ...restProps
    }: Props = $props();

    const ctx = getRadioRowContext();
    const disabled = $derived(givenDisabled || ctx.isDisabled);
    const checked = $derived(ctx.value === value);
    const tabindex = $derived(disabled ? undefined : ctx.tabStop === value ? 0 : -1);

    $effect(() => ctx.registerOption(value, disabled));

    // Spring the dot in/out on selection for a snappy, springy feel.
    const dotScale = new Spring(0, {stiffness: 0.3, damping: 0.6});
    $effect(() => {
        dotScale.target = checked ? 1 : 0;
    });

    function select() {
        if (disabled) return;
        ctx.value = value;
    }

    function onkeydown(event: KeyboardEvent) {
        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            select();
        }
    }
</script>

<MenuListItem active={checked}>
    {#snippet children({attach})}
        <div
            {@attach attach}
            {...mergeProps(
                {
                    class: ['radio-row', className],
                    'data-state': checked ? 'checked' : 'unchecked',
                    'data-disabled': disabled ? '' : undefined,
                },
                restProps,
            )}
        >
            <div
                class="radio-row-control"
                role="radio"
                aria-checked={checked ? 'true' : 'false'}
                aria-disabled={disabled ? 'true' : undefined}
                data-value={value}
                {tabindex}
                onclick={select}
                {onkeydown}
            >
                <span class="radio-row-indicator" aria-hidden="true">
                    <span class="radio-row-dot" style="transform: scale({dotScale.current})"></span>
                </span>
                <span class="radio-row-body">{@render content?.()}</span>
            </div>
            {#if trailing}
                <span class="radio-row-trailing">{@render trailing()}</span>
            {/if}
            <input
                class="radio-row-input"
                type="radio"
                name={ctx.name}
                {value}
                {disabled}
                {checked}
                tabindex={-1}
                aria-hidden="true"
            />
        </div>
    {/snippet}
</MenuListItem>

<style>
    /* No fill of its own — the group's list slides its highlights behind the
       row — and the list's row shape, sitting above those highlights. */
    .radio-row {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        border-radius: var(--corner-sm);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text);
    }

    /* Inset, so the list's horizontal clipping can't cut the ring off. */
    .radio-row:has(.radio-row-control:focus-visible) {
        outline: 2px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: -2px;
    }

    .radio-row[data-disabled] {
        color: var(--color-text-disabled);
    }

    /* The radio spans the row up to the trailing content, so the whole row
       (padding included) stays the click target. */
    .radio-row-control {
        display: flex;
        flex: 1;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        padding-block: var(--space-1_5);
        padding-inline: var(--space-2);
        cursor: pointer;
        outline: none;
        user-select: none;
    }

    .radio-row[data-disabled] .radio-row-control {
        cursor: not-allowed;
    }

    .radio-row-body {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .radio-row-trailing {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-2);
        padding-inline-end: var(--space-2);
    }

    /* An unfilled ring; when checked it takes the accent and a dot in the same
       ink springs in at its centre. */
    .radio-row-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: var(--space-4);
        height: var(--space-4);
        border: 1px solid var(--color-border-strong, var(--color-border));
        border-radius: var(--corner-full);
        transition: border-color var(--duration-fast) var(--easing-default);
    }

    .radio-row:hover:not([data-disabled], [data-state='checked']) .radio-row-indicator {
        border-color: var(--color-text-muted);
    }

    .radio-row[data-state='checked'] .radio-row-indicator {
        border-color: var(--color-interactive);
    }

    .radio-row[data-disabled] .radio-row-indicator {
        border-color: var(--color-border);
    }

    .radio-row-dot {
        width: var(--space-2);
        height: var(--space-2);
        border-radius: var(--corner-full);
        background: var(--color-interactive);
    }

    /* Keeps the real radio in the form without showing it; the control above
       carries the radio semantics. */
    .radio-row-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>
