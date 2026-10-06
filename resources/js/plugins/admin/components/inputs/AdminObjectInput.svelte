<script lang="ts">
    import { tick } from 'svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { emptyValue, inferControl, record, type Control, type NewValueType } from '../../forms/controls.js';
    import { useFieldLabel } from '../../forms/labels.js';
    import AdminValueInput from './AdminValueInput.svelte';
    import AdminValueTypeSelect from './AdminValueTypeSelect.svelte';

    let {
        id,
        label,
        control,
        value,
        onchange,
        disabled = false,
        secret = false
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        disabled?: boolean;
        secret?: boolean;
    } = $props();
    const { __ } = useTranslator();
    const fieldLabel = useFieldLabel();
    let newKey = $state('');
    let newType = $state<NewValueType>('text');
    let addError = $state('');
    let showAdd = $state(false);
    const object = $derived(record(value));
    /** Properties the admin added on top of the declared fields. */
    const entries = $derived(Object.entries(object).filter(([key]) => !Object.hasOwn(control.fields ?? {}, key)));
    function update(key: string, next: unknown) {
        const changed = { ...object };
        if (next === undefined) delete changed[key];
        else changed[key] = next;
        onchange(changed);
    }
    async function addEntry() {
        const key = newKey.trim();
        if (
            !key ||
            ['__proto__', 'constructor', 'prototype'].includes(key) ||
            Object.hasOwn(object, key) ||
            Object.hasOwn(control.fields ?? {}, key)
        ) {
            addError = __('admin.form.unique_key');
            return;
        }
        update(key, emptyValue(control.item?.type ?? newType));
        newKey = '';
        addError = '';
        showAdd = false;
        await tick();
        (
            document.getElementById(`${id}-custom-${key}`)?.querySelector<HTMLElement>('input,button,textarea') ??
            document.getElementById(`${id}-custom-${key}`) ??
            document.getElementById(`${id}-add-toggle`)
        )?.focus();
    }
    async function revealAdd() {
        showAdd = true;
        await tick();
        document.getElementById(`${id}-key`)?.focus();
    }
    function removeEntry(key: string) {
        (document.getElementById(`${id}-key`) ?? document.getElementById(`${id}-add-toggle`))?.focus();
        update(key, undefined);
    }
</script>

<div class="fields">
    {#each Object.entries(control.fields ?? {}) as [key, child] (key)}
        <AdminValueInput
            id={`${id}-${key}`}
            label={fieldLabel(key)}
            control={child}
            value={object[key]}
            onchange={(next) => update(key, next)}
            {disabled}
            {secret}
            nested
        />
    {/each}
    {#each entries as [key, child] (key)}
        <div class="entry">
            <AdminValueInput
                id={`${id}-custom-${key}`}
                label={key}
                control={control.item ?? inferControl(child)}
                value={child}
                onchange={(next) => update(key, next)}
                {disabled}
                {secret}
                nested
            />
            <Button
                type="button"
                size="sm"
                variant="ghost"
                aria-label={__('admin.form.remove_named', { name: key })}
                onclick={() => removeEntry(key)}>{__('admin.form.remove')}</Button
            >
        </div>
    {/each}
</div>
{#if control.custom && !showAdd}
    <Button
        id={`${id}-add-toggle`}
        type="button"
        size="sm"
        variant="ghost"
        class="add-toggle"
        {disabled}
        onclick={revealAdd}>{__('admin.form.add_property')}</Button
    >
{:else if control.custom}
    <div class="add-entry">
        <Input
            id={`${id}-key`}
            aria-label={__('admin.form.property_name', { name: label })}
            bind:value={newKey}
            {disabled}
        />
        {#if !control.item}<AdminValueTypeSelect
                value={newType}
                onchange={(next) => (newType = next)}
                {disabled}
            />{/if}
        <Button
            type="button"
            size="sm"
            variant="stroke"
            {disabled}
            onclick={addEntry}>{__('admin.form.add_property')}</Button
        >
    </div>
    {#if addError}<p role="alert">{addError}</p>{/if}
{/if}

<style>
    .fields {
        display: grid;
        gap: var(--space-4);
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
    .add-entry :global(input) {
        flex: 1;
        min-width: 8rem;
    }
    :global(.add-toggle) {
        justify-self: start;
        margin-block-start: var(--space-2);
    }
</style>
