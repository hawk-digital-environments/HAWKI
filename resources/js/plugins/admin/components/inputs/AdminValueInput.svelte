<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { Control } from '../../forms/controls.js';
    import AdminProviderIconInput from './AdminProviderIconInput.svelte';
    import AdminPricingInput from './AdminPricingInput.svelte';
    import AdminObjectInput from './AdminObjectInput.svelte';
    import AdminMultiInput from './AdminMultiInput.svelte';
    import AdminListInput from './AdminListInput.svelte';
    import AdminLocalizedTextInput from './AdminLocalizedTextInput.svelte';
    import AdminScalarInput from './AdminScalarInput.svelte';

    let {
        id,
        label,
        control,
        value,
        onchange,
        onselect,
        onBusyChange = () => {},
        onblur = () => {},
        disabled = false,
        error,
        secret = false,
        nested = false
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        /** Fires when the user picks one of `control.suggestions`, after `onchange` carried its value. */
        onselect?: (value: string) => void;
        onblur?: () => void;
        onBusyChange?: (busy: boolean) => void;
        disabled?: boolean;
        error?: string;
        secret?: boolean;
        nested?: boolean;
    } = $props();
    const { __ } = useTranslator();
    /** Composite controls render inside a fieldset that owns the legend, hint and error. */
    const grouped = $derived(
        ['object', 'multi', 'list', 'localized-text', 'markdown-locales', 'pricing'].includes(control.type)
    );
    const describedby = $derived(
        error ? `${id}-error`
        : control.hint ? `${id}-hint`
        : undefined
    );
</script>

{#if control.type === 'provider-icon'}
    <AdminProviderIconInput {onBusyChange} {id} {label} {value} {onchange} {onblur} {disabled} {error} />
{:else if grouped}
    <fieldset
        {id}
        {disabled}
        class:nested
        aria-describedby={describedby}
        data-invalid={!!error}
        onfocusout={onblur}
    >
        <legend>{label}</legend>
        {#if control.hint}<p
                id={`${id}-hint`}
                class="hint"
            >
                {__(control.hint)}
            </p>{/if}
        {#if control.type === 'object'}
            <AdminObjectInput {id} {label} {control} {value} {onchange} {disabled} {secret} />
        {:else if control.type === 'multi'}
            <AdminMultiInput {id} {label} {control} {value} {onchange} {disabled} />
        {:else if control.type === 'list'}
            <AdminListInput {id} {control} {value} {onchange} {disabled} {secret} />
        {:else if control.type === 'pricing'}
            <AdminPricingInput {id} {value} {onchange} {disabled} />
        {:else}
            <AdminLocalizedTextInput {id} {label} {control} {value} {onchange} {disabled} {error} {describedby} />
        {/if}
        {#if error}<p
                id={`${id}-error`}
                class="error"
            >
                {error}
            </p>{/if}
    </fieldset>
{:else}
    <AdminScalarInput {id} {label} {control} {value} {onchange} {onselect} {onblur} {disabled} {error} {secret} />
{/if}

<style>
    fieldset {
        border: var(--border);
        padding: var(--space-3);
        border-radius: var(--corner-md);
        min-width: 0;
    }
    legend {
        font-weight: 600;
        font-size: var(--font-size-sm);
        padding-inline: var(--space-1);
    }
    fieldset.nested {
        border: 0;
        border-inline-start: var(--border-strong);
        border-radius: 0;
        padding-block: 0;
        padding-inline: var(--space-3) 0;
    }
    fieldset[data-invalid='true'],
    fieldset.nested[data-invalid='true'] {
        border-color: var(--color-error);
    }
    fieldset[data-invalid='true'] > legend {
        color: var(--color-error);
    }
    fieldset.nested > legend {
        padding-inline: 0;
        margin-block-end: var(--space-2);
        color: var(--color-text-muted);
    }
    .hint,
    .error {
        font-size: var(--font-size-sm);
        margin-block: var(--space-2);
    }
    .hint {
        color: var(--color-text-muted);
    }
    .error {
        white-space: pre-line;
        color: var(--color-error);
        font-weight: 600;
    }
</style>
