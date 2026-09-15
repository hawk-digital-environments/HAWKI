<script lang="ts">
    import Input from '$lib/components/ui/input/Input.svelte';
    import Combobox from '$lib/components/ui/combobox/Combobox.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import Field from '$lib/components/ui/form/Field.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useFieldLabel } from '../../forms/labels.js';
    import type { Control } from '../../forms/controls.js';

    let {
        id,
        label,
        control,
        value,
        onchange,
        onselect,
        onblur = () => {},
        disabled = false,
        error,
        secret = false
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        /** Fires when the user picks one of `control.suggestions`, after `onchange` carried its value. */
        onselect?: (value: string) => void;
        onblur?: () => void;
        disabled?: boolean;
        error?: string;
        secret?: boolean;
    } = $props();
    const { __ } = useTranslator();
    const fieldLabel = useFieldLabel();
    const selectedOption = $derived(control.options?.find((option) => option.value === value));
    const inputType = $derived(
        control.type === 'secret' || (secret && control.type === 'text') ? 'password'
        : control.type === 'datetime' ? 'datetime-local'
        : control.type === 'number' ? 'number'
        : control.type === 'url' ? 'url'
        : 'text'
    );
    function inputValue(input: HTMLInputElement): unknown {
        if (control.type === 'number') return input.value === '' ? undefined : input.valueAsNumber;
        return control.optional && input.value === '' ? undefined : input.value;
    }
</script>

<Field
    {id}
    {label}
    {error}
    hint={control.hint ? __(control.hint) : undefined}
>
    {#snippet children(props)}
        {#if control.type === 'boolean' && control.optional}
            <SingleSelect
                triggerProps={{
                    ...props,
                    'aria-label': `${label}: ${__(
                        value === true ? 'admin.yes'
                        : value === false ? 'admin.no'
                        : 'admin.form.inherit'
                    )}`,
                    onblur
                }}
                {disabled}
                value={value === true ? 'true'
                : value === false ? 'false'
                : 'default'}
                onValueChange={(next: string) => onchange(next === 'default' ? undefined : next === 'true')}
                items={[
                    { value: 'default', label: __('admin.form.inherit') },
                    { value: 'true', label: __('admin.yes') },
                    { value: 'false', label: __('admin.no') }
                ]}
            />
        {:else if control.type === 'boolean'}
            <Switch
                {...props}
                aria-label={label}
                bind:checked={() => value === true, (next) => onchange(next)}
                {disabled}
                {onblur}
            />
        {:else if control.type === 'select'}
            <SingleSelect
                triggerProps={{
                    ...props,
                    'aria-label': `${label}: ${selectedOption ? fieldLabel(selectedOption.label) : __('admin.choose')}`,
                    onblur
                }}
                {disabled}
                placeholder={__('admin.choose')}
                items={(control.options ?? []).map((option) => ({
                    value: String(option.value),
                    label: fieldLabel(option.label)
                }))}
                value={value == null ? '' : String(value)}
                onValueChange={(next: string) =>
                    onchange(control.options?.find((option) => String(option.value) === next)?.value ?? '')}
            />
        {:else if control.type === 'textarea'}
            <Textarea
                {...props}
                rows={6}
                value={String(value ?? '')}
                oninput={(event) => onchange(event.currentTarget.value)}
                {onblur}
                {disabled}
            />
        {:else if control.suggestions}
            <Combobox
                value={value == null ? '' : String(value)}
                items={control.suggestions}
                onValueChange={(next) => onchange(control.optional && next === '' ? undefined : next)}
                onSelect={(item) => onselect?.(item.value)}
                {disabled}
                inputProps={{ ...props, onblur }}
                emptyText={__('admin.form.suggestions_empty')}
                moreText={(hidden) => __('admin.form.suggestions_more', { count: String(hidden) })}
                toggleLabel={__('admin.form.suggestions_toggle', { name: label })}
            />
        {:else}
            <!-- The form owns the value: a no-op setter keeps programmatic updates flowing
                 after the user edited the field. -->
            <Input
                {...props}
                type={inputType}
                min={control.min}
                max={control.max}
                step={control.step}
                bind:value={() => (value == null ? '' : String(value)), () => {}}
                oninput={(event) => onchange(inputValue(event.currentTarget))}
                {onblur}
                {disabled}
                autocomplete={secret || control.type === 'secret' ? 'new-password' : 'off'}
            />
        {/if}
    {/snippet}
</Field>
