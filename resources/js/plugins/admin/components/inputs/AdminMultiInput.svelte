<script lang="ts">
    import Input from '$lib/components/ui/input/Input.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { Control } from '../../forms/controls.js';
    import { useFieldLabel } from '../../forms/labels.js';

    let {
        id,
        label,
        control,
        value,
        onchange,
        disabled = false
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        disabled?: boolean;
    } = $props();
    const { __ } = useTranslator();
    const fieldLabel = useFieldLabel();
    let search = $state('');
    let newItem = $state('');
    const items = $derived(Array.isArray(value) ? value : []);
    /** Declared options first, then any custom values already stored on the row. */
    const options = $derived([
        ...(control.options ?? []),
        ...items
            .filter((item) => !control.options?.some((option) => option.value === item))
            .map((item) => ({ value: typeof item === 'number' ? item : String(item), label: String(item) }))
    ]);
    const visible = $derived(
        options.filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase()))
    );
    function optionLabel(option: { value: string | number; label: string }) {
        return control.label === 'permissions' ?
                __('admin.permissions.' + String(option.value).replaceAll('.', '_'))
            :   fieldLabel(option.label);
    }
    function toggle(optionValue: string | number, checked: boolean) {
        onchange(checked ? [...items, optionValue] : items.filter((item) => item !== optionValue));
    }
    function addItem() {
        const item = newItem.trim();
        if (!items.includes(item)) onchange([...items, item]);
        newItem = '';
    }
</script>

{#if options.length > 10}<Input
        aria-label={__('admin.form.filter', { name: label })}
        type="search"
        bind:value={search}
        {disabled}
    />{/if}
<div class="choices">
    {#each visible as option (option.value)}
        <label
            class="choice"
            data-capability={control.label === 'native_capabilities' ? String(option.value) : undefined}
            ><input
                type="checkbox"
                {disabled}
                checked={items.includes(option.value)}
                onchange={(event) => toggle(option.value, event.currentTarget.checked)}
            />
            <span>{optionLabel(option)}</span>
        </label>
    {/each}
</div>
{#if control.custom}
    <div class="add-entry">
        <Input
            id={`${id}-add`}
            aria-label={__('admin.form.new_item', { name: label })}
            bind:value={newItem}
            {disabled}
        /><Button
            type="button"
            size="sm"
            variant="stroke"
            disabled={disabled || !newItem.trim()}
            onclick={addItem}>{__('admin.form.add')}</Button
        >
    </div>
{/if}

<style>
    .choices {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-2);
        max-height: 18rem;
        overflow-y: auto;
        margin-block: var(--space-2);
    }
    .choice {
        display: flex;
        align-items: start;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        padding: var(--space-1);
        overflow-wrap: anywhere;
    }
    .choice input {
        margin-top: 0.2em;
    }
    .choice[data-capability] {
        color: var(--capability-color);
        border-radius: var(--corner-xs);
        background-color: var(--capability-surface);
    }
    .choice[data-capability] input {
        accent-color: var(--capability-color);
    }
    .add-entry {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: var(--space-2);
        margin-block: var(--space-3);
    }
    .add-entry :global(input) {
        flex: 1;
        min-width: 8rem;
    }
    @media (--bp-sm-and-smaller) {
        .choices {
            grid-template-columns: 1fr;
        }
    }
</style>
