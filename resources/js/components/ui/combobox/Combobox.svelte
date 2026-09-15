<script module lang="ts">
    export interface ComboboxItemDefinition {
        /** The value written into the input when the item is picked. */
        value: string;
        /** Optional display name shown next to the value. */
        label?: string;
    }
</script>

<!--
  @component Text input with a filtered suggestion list. Unlike `SingleSelect`, any
  text is accepted: the suggestions are shortcuts, not the only valid values.
  Built on `bits-ui`'s `Combobox` so keyboard navigation, `aria-activedescendant`
  and the listbox semantics come from the primitive.

  @example
  ```svelte
  <Combobox
      value={modelId}
      items={[{value: 'gpt-4o', label: 'GPT-4o'}]}
      onValueChange={(next) => (modelId = next)}
      onSelect={(item) => loadMetadata(item.value)}
      emptyText="No matches"
      moreText={(hidden) => `${hidden} more`}
      toggleLabel="Show suggestions"
  />
  ```
-->
<script lang="ts">
    import { Combobox as ComboboxPrimitive, mergeProps } from 'bits-ui';
    import type { HTMLInputAttributes } from 'svelte/elements';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import ComboboxList from './ComboboxList.svelte';

    let {
        value = '',
        items = [],
        onValueChange,
        onSelect,
        disabled = false,
        maxItems = 100,
        emptyText,
        moreText,
        toggleLabel,
        inputProps = {}
    }: {
        /** Current text; the component is controlled and reports every change through `onValueChange`. */
        value?: string;
        items?: ComboboxItemDefinition[];
        onValueChange: (value: string) => void;
        /** Fires after `onValueChange` when the user picks an item from the list. */
        onSelect?: (item: ComboboxItemDefinition) => void;
        disabled?: boolean;
        /** Cap on rendered items; the rest is summarized through `moreText`. */
        maxItems?: number;
        emptyText: string;
        moreText: (hidden: number) => string;
        toggleLabel: string;
        /** Attributes for the text input, e.g. `id`, `aria-describedby`, `aria-invalid`, `onblur`. */
        inputProps?: HTMLInputAttributes;
    } = $props();

    let open = $state(false);
    const needle = $derived(value.trim().toLowerCase());
    const matches = $derived(
        needle ?
            items.filter(
                (item) =>
                    item.value.toLowerCase().includes(needle) || (item.label?.toLowerCase().includes(needle) ?? false)
            )
        :   items
    );
    const shown = $derived(matches.slice(0, maxItems));
    // Only an explicit pick counts as selected; typed text that happens to equal an item must still be pickable.
    let picked = $state('');
    const selected = $derived(picked !== '' && picked === value ? picked : '');

    function setText(next: string) {
        if (next !== value) onValueChange(next);
    }
</script>

<ComboboxPrimitive.Root
    type="single"
    bind:open
    value={selected}
    onValueChange={(next: string) => {
        const item = items.find((entry) => entry.value === next);
        if (!item) return;
        picked = item.value;
        setText(item.value);
        onSelect?.(item);
    }}
    inputValue={value}
    allowDeselect={false}
    {disabled}
>
    <div class="combobox">
        <ComboboxPrimitive.Input
            {...mergeProps(
                {
                    class: 'combobox-input',
                    autocomplete: 'off',
                    oninput: (event: Event & { currentTarget: HTMLInputElement }) =>
                        setText(event.currentTarget.value),
                    onclick: () => {
                        if (!disabled) open = true;
                    }
                },
                inputProps as Record<string, unknown>
            ) as ComboboxPrimitive.InputProps}
        />
        <ComboboxPrimitive.Trigger
            class="combobox-toggle"
            aria-label={toggleLabel}
            {disabled}
        >
            <ChevronDownIcon size={18} />
        </ComboboxPrimitive.Trigger>
    </div>
    <ComboboxList
        items={shown}
        itemLabel={(item) => item.value}
        {emptyText}
        moreText={matches.length > shown.length ? moreText(matches.length - shown.length) : undefined}
    >
        {#snippet item(entry)}
            <span class="value">{entry.value}</span>
            {#if entry.label && entry.label !== entry.value}
                <span class="label">{entry.label}</span>
            {/if}
        {/snippet}
    </ComboboxList>
</ComboboxPrimitive.Root>

<style>
    .combobox {
        position: relative;
        display: flex;
        align-items: center;
        min-width: 0;
    }
    :global(.combobox-input) {
        width: 100%;
        min-height: 2.75rem;
        box-sizing: border-box;
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        color: var(--color-text);
        padding: 0 3rem 0 var(--space-3);
        font: inherit;

        &[aria-invalid='true'] {
            border-color: var(--color-error);
        }
        &:focus-visible {
            outline: 2px solid var(--color-focus-ring);
            outline-offset: 2px;
        }
        &:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
    }
    :global(.combobox-toggle) {
        position: absolute;
        inset-inline-end: var(--space-2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border: 0;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text-muted);
        cursor: pointer;

        &:hover {
            background: var(--color-hover);
        }
        &:focus-visible {
            outline: 2px solid var(--color-focus-ring);
            outline-offset: 2px;
        }
        &:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        &[data-state='open'] :global(svg) {
            transform: rotate(-180deg);
        }
    }
    .value {
        overflow-wrap: anywhere;
    }
    .label {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }
</style>
