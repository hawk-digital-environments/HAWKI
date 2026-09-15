<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';
    import { getContext } from 'svelte';
    import type { RadioGroupContext } from './RadioSwitch.svelte';

    let {
        value,
        label,
        description,
        icon,
        variant = 'card',
        disabled = false,
        leading = undefined,
        meta = undefined,

    } = $props<{
        value: string;
        label: string;
        description?: string;
        icon?: IconComponent;
        variant?: 'default' | 'card';
        /** Disables the option: not toggleable, rendered muted. */
        disabled?: boolean;
        /** Optional leading snippet rendered in place of the `icon` component (e.g. a per-row custom icon like `ToolIcon`). */
        leading?: import('svelte').Snippet | undefined;
        /** Optional trailing content rendered between the text and the selection indicator (e.g. a status dot). */
        meta?: import('svelte').Snippet | undefined;
    }>();

    const ctx = getContext<RadioGroupContext>('radioGroup');
    const isSelected = $derived(ctx.isSelected(value));
    const multiple = $derived(ctx.multiple);

    // Register with the group so its proximity-hover container can measure this
    // row and slide the highlights behind it.
    let buttonEl = $state<HTMLButtonElement | null>(null);
    let index = $state(-1);

    $effect(() => {
        if (!buttonEl) return;
        const assigned = ctx.register(buttonEl);
        index = assigned;
        return () => {
            ctx.unregister(assigned);
            index = -1;
        };
    });

    $effect(() => {
        if (index >= 0) ctx.setState(index, { selected: isSelected });
    });

    function select(): void {
        if (disabled) return;
        ctx.select(value);
    }
</script>

<button
        type="button"
        class="radio-option"
        class:variantDefault={variant === 'default'}
        class:variantCard={variant === 'card'}
        class:isSelected={isSelected}
        class:isDisabled={disabled}
        disabled={disabled}
        aria-disabled={disabled}
        bind:this={buttonEl}
        onclick={select}
>
    {#if leading}
        <span class="icon">
            {@render leading()}
        </span>
    {:else if icon}
        {@const IconCmp = icon}
        <span class="icon"><IconCmp size="1em" /></span>
    {/if}
    <span class="text-wrapper">
        <span class="label">{label}</span>
        <span class="description">{description}</span>
    </span>
    {#if meta}
        <span class="meta">
            {@render meta()}
        </span>
    {/if}
    <span
        class="radio-indicator"
        class:checkbox={multiple}
        class:checked={isSelected}
        aria-hidden="true"
    ></span>
    <input
        type={multiple ? 'checkbox' : 'radio'}
        name={ctx.name}
        {value}
        checked={isSelected}
        class="sr-only"
        tabindex="-1"
        disabled={disabled}
        onchange={select}
    />
</button>


<style>
    button{
        position: relative;
        z-index: 1;
        border: none;
        display: flex;
        flex-direction: row;
        gap: .5rem;
        align-items: center;
        background: none;
        cursor: pointer;
    }
    .text-wrapper{
        display: flex;
        flex: 1;
        min-width: 0;
        flex-direction: column;
        justify-content: start;
        text-align: start;
        padding: .5rem 0;
    }
    .icon{
        font-size: var(--font-size-lg);
        margin-right: .5rem;
    }

    .description{
        font-size: var(--font-size-xs);
    }

    .meta{
        display: flex;
        flex-shrink: 0;
        align-items: center;
    }

    button:disabled{
        cursor: not-allowed;
    }

    button.isDisabled .label,
    button.isDisabled .description,
    button.isDisabled .icon{
        color: var(--color-text-muted);
        opacity: .7;
    }

    /* Radio indicator: an outlined circle that fills with a dot when selected. */
    .radio-indicator{
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        border-radius: var(--corner-full);
        border: 1.5px solid var(--color-border-strong);
        position: relative;
        transition:
            border-color var(--duration-fast),
            background var(--duration-fast);
    }
    .radio-indicator.checked{
        border-color: var(--color-accent-text);
    }
    .radio-indicator.checked::after{
        content: '';
        position: absolute;
        inset: 3px;
        border-radius: var(--corner-full);
        background: var(--color-accent-text);
    }

    /* Multiselect indicator: a rounded rectangle that fills with a smaller
       rounded rectangle when selected, instead of the single-select dot. */
    .radio-indicator.checkbox{
        border-radius: 6px;
    }
    /* Concentric with the 6px outer radius: inner = outer − 3px inset. */
    .radio-indicator.checkbox.checked::after{
        border-radius: 3px;
    }

    button.variantCard{
        padding: 0.5rem 1.5rem 0.5rem 1rem;
        border-radius: var(--corner-md);
    }
    button.variantCard.isSelected{
        color: var(--color-accent-text);
    }
    button.variantCard.isSelected .label{
        color: var(--color-accent-text);
    }
    button.variantCard input{
        display: none;
    }

    button.variantCard .description{
        color: var(--color-text-muted)
    }
    button.variantCard.isSelected .icon{
        color: var(--color-accent-text);
    }
</style>
