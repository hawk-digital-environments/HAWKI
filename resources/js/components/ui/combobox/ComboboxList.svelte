<!--
  @component Floating listbox shared by `Combobox` and `MultiCombobox`: the panel, its
  scroll viewport, one option per item and the disabled notes for "no matches" and
  "n more". Must be rendered inside a `bits-ui` `Combobox.Root`. The elements are
  rendered here through `bits-ui`'s `child` snippets, so the styles below stay
  component-scoped and either picker renders correctly on its own; callers only
  supply the option content.

  @example
  ```svelte
  <ComboboxList items={shown} itemLabel={(item) => item.label} emptyText="No matches">
      {#snippet item(entry, { selected })}
          {#if selected}<CheckIcon size={16} />{/if}{entry.label}
      {/snippet}
  </ComboboxList>
  ```
-->
<script
    lang="ts"
    generics="T extends { value: string }"
>
    import type { Snippet } from 'svelte';
    import { Combobox as ComboboxPrimitive } from 'bits-ui';

    /** State `bits-ui` reports for an option; the namespace does not re-export this type. */
    interface ComboboxListItemState {
        selected: boolean;
        highlighted: boolean;
    }

    let {
        items,
        itemLabel,
        item,
        emptyText,
        moreText,
        customAnchor = null
    }: {
        /** Options to render, already filtered and capped by the caller. */
        items: T[];
        /** Text `bits-ui` uses for typeahead and, in single mode, writes into the input on pick. */
        itemLabel: (item: T) => string;
        /** Option content; receives the item and `bits-ui`'s `selected` / `highlighted` state. */
        item: Snippet<[T, ComboboxListItemState]>;
        /** Shown as a disabled note when `items` is empty. */
        emptyText: string;
        /** Shown as a disabled note after the options, e.g. "12 more"; omit when nothing is hidden. */
        moreText?: string;
        /** Element the panel is anchored to when it is not the `bits-ui` input. */
        customAnchor?: HTMLElement | null;
    } = $props();

    // Disabled notes share the listbox with the options; a NUL-prefixed value keeps their
    // ids from colliding with any real item value.
    const NOTE = '\u0000';
</script>

<ComboboxPrimitive.Portal>
    <ComboboxPrimitive.Content
        sideOffset={4}
        {customAnchor}
    >
        {#snippet child({ props, wrapperProps })}
            <div {...wrapperProps}>
                <div
                    {...props}
                    class="content"
                >
                    <ComboboxPrimitive.Viewport>
                        {#snippet child({ props })}
                            <div
                                {...props}
                                class="viewport"
                            >
                                {#each items as entry (entry.value)}
                                    <ComboboxPrimitive.Item
                                        value={entry.value}
                                        label={itemLabel(entry)}
                                    >
                                        {#snippet child({ props, selected, highlighted })}
                                            <div
                                                {...props}
                                                class="item"
                                            >
                                                {@render item(entry, { selected, highlighted })}
                                            </div>
                                        {/snippet}
                                    </ComboboxPrimitive.Item>
                                {/each}
                                {#if items.length === 0}
                                    <ComboboxPrimitive.Item
                                        value={`${NOTE}empty`}
                                        label=""
                                        disabled
                                    >
                                        {#snippet child({ props })}
                                            <div
                                                {...props}
                                                class="item note"
                                            >
                                                {emptyText}
                                            </div>
                                        {/snippet}
                                    </ComboboxPrimitive.Item>
                                {:else if moreText}
                                    <ComboboxPrimitive.Item
                                        value={`${NOTE}more`}
                                        label=""
                                        disabled
                                    >
                                        {#snippet child({ props })}
                                            <div
                                                {...props}
                                                class="item note"
                                            >
                                                {moreText}
                                            </div>
                                        {/snippet}
                                    </ComboboxPrimitive.Item>
                                {/if}
                            </div>
                        {/snippet}
                    </ComboboxPrimitive.Viewport>
                </div>
            </div>
        {/snippet}
    </ComboboxPrimitive.Content>
</ComboboxPrimitive.Portal>

<style>
    .content {
        z-index: var(--layer-overlay);
        box-sizing: border-box;
        width: var(--bits-combobox-anchor-width);
        max-height: calc(var(--bits-combobox-content-available-height, 999px) - var(--space-4));
        overflow: hidden;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--color-surface-raised);
        color: var(--color-text);
        box-shadow: var(--elevation-2);
        padding: var(--space-1);
    }
    .viewport {
        max-height: calc(var(--bits-combobox-content-available-height, 999px) - var(--space-6));
        overflow-y: auto;
    }
    .item {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--space-1) var(--space-2);
        padding: var(--space-2);
        border-radius: var(--corner-sm);
        font-size: var(--font-size-sm);
        cursor: pointer;
        outline: none;

        &[data-highlighted] {
            background-color: var(--color-surface);
        }
        &[data-selected] {
            background-color: var(--color-highlight);
            font-weight: var(--font-weight-medium, 500);
        }
        &[data-disabled] {
            cursor: default;
        }
    }
    .note {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
</style>
