<!--
  @component Test host for `SingleSelect.svelte`. Wraps the select with a
  native `<label for>` association (via `triggerProps.id`) so tests can query
  the trigger through accessible label queries, and mirrors the bound `value`
  into the DOM (`output.host-value`) so two-way binding is observable.
-->
<script lang="ts">
    import SingleSelect, {type ItemSnippetProps, type SelectItemDefinition} from '../SingleSelect.svelte';
    import type {Snippet} from 'svelte';

    let {
        label = 'Select',
        value = $bindable(),
        items,
        placeholder,
        triggerProps,
        itemSnippet,
        onValueChange
    }: {
        label?: string;
        value?: string | null | undefined;
        items: SelectItemDefinition[];
        placeholder?: string;
        triggerProps?: Record<string, any>;
        itemSnippet?: Snippet<[ItemSnippetProps]>;
        onValueChange?: (value: string) => void;
    } = $props();
</script>

<label for="single-select-trigger">{label}</label>
<SingleSelect
    bind:value={value as never}
    {items}
    {placeholder}
    {itemSnippet}
    {onValueChange}
    triggerProps={{id: 'single-select-trigger', ...triggerProps}}
/>
<output class="host-value">{JSON.stringify(value ?? null)}</output>
