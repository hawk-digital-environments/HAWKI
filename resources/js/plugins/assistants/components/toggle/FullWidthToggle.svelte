<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';
    import Switch from "./Switch.svelte";
    import InputError from "$plugins/assistants/components/inputError/InputError.svelte";

    let {
        label,
        description,
        icon,
        defaultValue = false,
        toggleType = 'switch',
        switchPosition = 'right',
        borderless = false,
        error,
        onchange,
        disabled = false,
    } = $props <{
        id?: string;
        label: string;
        description?: string;
        defaultValue?: boolean;
        icon?: IconComponent;
        toggleType?: "switch" | "checkbox" | "roundCheckbox" | "radio";
        switchPosition?: "right" | "left";
        borderless?: boolean;
        error?: string;
        onchange: (value: boolean) => void;
        disabled?: boolean;
    }>();

</script>


<div class="toggle-wrapper"
     class:withIcon={icon}
     class:isDisabled={disabled}
     class:switchRight={switchPosition === "right"}
     class:switchLeft={switchPosition === "left"}
     class:borderless={borderless}
>
    {#if icon}
        {@const IconCmp = icon}
        <div class="icon-wrapper">
            <span class="icon"><IconCmp size="1em" /></span>
        </div>
    {/if}
    <div class="text-wrapper">
        <div class="field-header">
            <p class="label">{label}</p>
            <InputError message={error} />
        </div>
        {#if description}
            <p class="description">{description}</p>
        {/if}
    </div>
    <div class="switch-wrapper">
        <Switch
            defaultValue={defaultValue}
            onchange={onchange}
            toggleType={toggleType}
            disabled={disabled}
        />
    </div>
</div>

<style>
    .toggle-wrapper{
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-4);
        background: var(--color-surface-raised);
    }
    .toggle-wrapper.isDisabled{
        opacity: .75;
        background: var(--color-hover);
    }

    .toggle-wrapper.borderless {
        border: none;
        border-radius: 0;
        padding: var(--space-4) 0;
    }
    .toggle-wrapper.withIcon{
        gap: var(--space-4);
        grid-template-columns: auto 1fr auto;
    }

    .toggle-wrapper.switchLeft {
        grid-template-columns: auto 1fr;
        gap: var(--space-4);
    }
    .toggle-wrapper.switchLeft > .switch-wrapper {
        grid-column: 1;
        grid-row: 1;
    }
    .toggle-wrapper.switchLeft > .text-wrapper {
        grid-column: 2;
        grid-row: 1;
    }
    .description{
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        margin: 0;
    }
    .icon-wrapper{
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-muted);
    }
    .icon{
        font-size: var(--font-size-lg);
    }
</style>




