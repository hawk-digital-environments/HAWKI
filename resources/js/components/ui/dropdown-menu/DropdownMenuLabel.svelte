<!--
  @component A non-interactive label displayed above a group of related menu
  items inside a `DropdownMenu` or `DropdownMenuGroup`. Set `inset` to align
  with indented items (e.g. those with a leading icon, like `DropdownMenuItem`
  with an `icon`, or a radio/checkbox indicator).

  ```svelte
  <DropdownMenu trigger="Tools">
      <DropdownMenuLabel>{__('chat.composer.toolMenu.capabilitiesLabel')}</DropdownMenuLabel>
      {#each capabilities as tool}
          <DropdownMenuCheckboxItem checked={tool.active}>{tool.name}</DropdownMenuCheckboxItem>
      {/each}
  </DropdownMenu>
  ```

  With `collapsible` the label becomes the section's disclosure: it renders as a menu item
  (so arrow keys reach it and Enter/Space work), carries `aria-expanded`, and shows a chevron
  in the same leading column an item's indicator uses, so its text lines up with the rows
  below it. It keeps the label's own typography and takes no hover fill — it is a heading
  first — but does highlight under keyboard focus. The caller owns the state and hides the
  rows itself; `DropdownMenuSection` is this label already paired with its rows and their
  collapse transition, which is what most menus want:

  ```svelte
  <DropdownMenuLabel collapsible expanded={open} onToggle={() => (open = !open)}>
      {provider.label}
  </DropdownMenuLabel>
  {#if open}
      {#each provider.models as model}…{/each}
  {/if}
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import ChevronDownIcon from '../icons/iconset/ChevronDownIcon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Ref to the root element. */
        ref?: HTMLDivElement | null;
        /** When true, adds left padding to align with indented menu items. */
        inset?: boolean;
        /** Turns the label into the section's disclosure control. The caller still decides
         *  what to render for the section — this only reports the toggle. */
        collapsible?: boolean;
        /** Whether the section this label heads is open. Drives the chevron and
         *  `aria-expanded`. Only meaningful with `collapsible`. @defaultValue true */
        expanded?: boolean;
        /** Called with the state the section should take when the label is activated. */
        onToggle?: (expanded: boolean) => void;
        /** Label text or rich content. */
        children?: Snippet;
    }

    let {
        ref = $bindable(null),
        inset = false,
        collapsible = false,
        expanded = true,
        onToggle,
        children,
        class: className,
        ...restProps
    }: Props = $props();

    const labelClass = $derived([
        'dropdown-label',
        collapsible && 'dropdown-label--collapsible',
        className
    ]);
</script>

{#if collapsible}
    <DropdownMenuPrimitive.Item closeOnSelect={false} onSelect={() => onToggle?.(!expanded)}>
        {#snippet child({props})}
            <div
                bind:this={ref}
                data-slot="dropdown-menu-label"
                aria-expanded={expanded}
                {...mergeProps({class: labelClass}, restProps, props)}
            >
                <span class="dropdown-label-chevron" class:dropdown-label-chevron--collapsed={!expanded}>
                    <ChevronDownIcon size={12}/>
                </span>
                {@render children?.()}
            </div>
        {/snippet}
    </DropdownMenuPrimitive.Item>
{:else}
    <div
        bind:this={ref}
        data-slot="dropdown-menu-label"
        data-inset={inset || undefined}
        {...mergeProps({class: labelClass}, restProps)}
    >
        {@render children?.()}
    </div>
{/if}

<style>
    .dropdown-label {
        /* A row, so a heading can carry an adornment — an info popover, a count — beside its
           text without the caller having to lay the label out again. */
        display: flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        /* A step below muted: a section heading is a landmark, not content, so it should
           sit behind the rows it heads. Softened by alpha rather than a lower token, since
           the next one down (`--color-text-disabled`) means something else and lands on the
           wrong side of muted in the dark theme. */
        color: color-mix(in oklch, var(--color-text-muted) 80%, transparent);
    }

    .dropdown-label[data-inset] {
        padding-left: var(--space-8, calc(0.25rem * 8));
    }

    /* The chevron takes the leading column an item's indicator would, so the heading's text
       starts where the rows' labels do. */
    .dropdown-label--collapsible {
        position: relative;
        padding-left: var(--space-8, calc(0.25rem * 8));
        border-radius: var(--corner-sm);
        cursor: pointer;
        outline: none;
        user-select: none;
    }

    /* A heading, so no hover fill — the chevron is the affordance. Keyboard navigation is
       another matter: `:focus-visible` stays off for the mouse, so it can say where it is. */
    .dropdown-label--collapsible[data-highlighted] {
        background-color: transparent;
    }

    .dropdown-label--collapsible:focus-visible {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    :global(.dropdown-content--sheet) .dropdown-label--collapsible {
        min-height: 2.5rem;
    }

    .dropdown-label-chevron {
        position: absolute;
        left: var(--space-2, calc(0.25rem * 2));
        display: flex;
        height: calc(0.25rem * 3.5);
        width: calc(0.25rem * 3.5);
        align-items: center;
        justify-content: center;
        transition: transform var(--duration-fast, 150ms) var(--easing-default, ease);
    }

    /* Down while the section is open, pointing at its rows; right when they are folded. */
    .dropdown-label-chevron--collapsed {
        transform: rotate(-90deg);
    }
</style>
