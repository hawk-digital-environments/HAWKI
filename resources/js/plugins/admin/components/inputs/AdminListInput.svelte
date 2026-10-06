<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { emptyValue, inferControl, type Control, type NewValueType } from '../../forms/controls.js';
    import AdminValueInput from './AdminValueInput.svelte';
    import AdminValueTypeSelect from './AdminValueTypeSelect.svelte';

    let {
        id,
        control,
        value,
        onchange,
        disabled = false,
        secret = false
    }: {
        id: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        disabled?: boolean;
        secret?: boolean;
    } = $props();
    const { __ } = useTranslator();
    let newType = $state<NewValueType>('text');
    const items = $derived(Array.isArray(value) ? value : []);
    function replaceItem(index: number, next: unknown) {
        onchange(items.map((current, i) => (i === index ? next : current)));
    }
    /** Keeps focus on a neighbouring row (or the add button) so keyboard users are not dropped. */
    function removeItem(index: number, event: MouseEvent) {
        const row = (event.currentTarget as HTMLElement).closest('li');
        const target =
            row?.nextElementSibling?.querySelector<HTMLElement>('input,button') ??
            row?.previousElementSibling?.querySelector<HTMLElement>('input,button') ??
            document.getElementById(`${id}-add`);
        target?.focus();
        onchange(items.filter((_, i) => i !== index));
    }
</script>

<ol class="list">
    {#each items as item, index}
        <li class="entry">
            <AdminValueInput
                id={`${id}-${index}`}
                label={__('admin.form.item', { number: String(index + 1) })}
                control={control.item ?? inferControl(item)}
                value={item}
                onchange={(next) => replaceItem(index, next)}
                {disabled}
                {secret}
                nested
            />
            <Button
                type="button"
                size="sm"
                variant="ghost"
                {disabled}
                aria-label={__('admin.form.remove_item', { number: String(index + 1) })}
                onclick={(event) => removeItem(index, event)}>{__('admin.form.remove')}</Button
            >
        </li>
    {/each}
</ol>
<div class="add-entry">
    {#if !control.item}<AdminValueTypeSelect
            value={newType}
            onchange={(next) => (newType = next)}
            {disabled}
        />{/if}
    <Button
        id={`${id}-add`}
        type="button"
        size="sm"
        variant="stroke"
        {disabled}
        onclick={() => onchange([...items, emptyValue(control.item?.type ?? newType)])}>{__('admin.form.add')}</Button
    >
</div>

<style>
    .list {
        padding: 0;
        list-style: none;
        display: grid;
        gap: var(--space-3);
    }
    .entry {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: var(--space-2);
        align-items: end;
    }
    .add-entry {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: var(--space-2);
        margin-block: var(--space-3);
    }
</style>
