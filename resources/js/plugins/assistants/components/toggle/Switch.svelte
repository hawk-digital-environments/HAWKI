<script lang="ts">

    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import { untrack } from 'svelte';
    import "./switch.css"

    let {
        size = "medium",
        defaultValue = false,
        onchange,
        toggleType = "switch",
        disabled = false,
    } = $props<{
        size?: "small" | "medium" | "large";
        defaultValue?: boolean;
        onchange?: (value: boolean) => void;
        toggleType?: "switch" | "checkbox" | "roundCheckbox" | "radio";
        disabled?: boolean;
    }>();

    // eslint-disable-next-line svelte/state_referenced_locally
    let isActive = $state(untrack(() => defaultValue));

    function onclick() {
        if (disabled) return;
        isActive = !isActive;
        onchange?.(isActive);
    }
</script>

{#if toggleType === "switch"}
    <!--
      Pill switch ported from the shared design-system Switch primitive
      (hawki-frontend-only: components/ui-hawki-import/switch). Kept interactive here (button)
      so the existing defaultValue/onchange/disabled API is preserved.
    -->
    <button
        type="button"
        role="switch"
        aria-checked={isActive}
        aria-label="toggle"
        {onclick}
        {disabled}
        class="switch"
        data-state={isActive ? 'checked' : 'unchecked'}
        data-inactive={!isActive && !disabled ? '' : undefined}
        data-disabled={disabled ? '' : undefined}
    >
        <span class="switch-thumb"></span>
    </button>
{:else}
    <!-- Legacy checkbox / roundCheckbox / radio variants (switch.css). -->
    <button
        type="button"
        role="switch"
        aria-checked={isActive}
        aria-label="toggle"
        {onclick}
        {disabled}
        class="switchButton"
        class:typeCheckbox={toggleType === "checkbox"}
        class:typeRoundCheckbox={toggleType === "roundCheckbox"}
        class:typeRadio={toggleType === "radio"}
        class:active={isActive}
        class:sizeSmall={size === "small"}
        class:sizeMedium={size === "medium"}
        class:sizeLarge={size === "large"}
    >
        <span class="toggle-area">
            <span class="toggle">
                {#if toggleType === "checkbox" || toggleType === "roundCheckbox"}
                    <Tick02Icon size="1em" />
                {/if}
            </span>
        </span>
    </button>
{/if}

<style>
    .switch {
        display: inline-flex;
        cursor: pointer;
        height: calc(0.25rem * 4);
        width: calc(0.25rem * 7);
        flex-shrink: 0;
        align-items: center;
        padding: 1px;
        border: none;
        border-radius: var(--corner-full, 999px);
        background-color: color-mix(in oklch, var(--color-text-muted) 35%, var(--color-bg));
        transition: background-color var(--duration-fast, 150ms);
    }

    .switch[data-state='checked'] {
        background-color: var(--color-interactive);
    }

    .switch[data-inactive]:hover {
        background-color: color-mix(in oklch, var(--color-text-muted) 45%, var(--color-bg));
    }

    .switch[data-disabled] {
        cursor: not-allowed;
        background-color: var(--color-disabled-bg);
    }

    .switch-thumb {
        display: block;
        height: calc(0.25rem * 3.5);
        width: calc(0.25rem * 3.5);
        border-radius: var(--corner-full, 999px);
        background-color: var(--color-bg);
        box-shadow: var(--elevation-1, 0 1px 2px rgb(0 0 0 / 0.18));
        transition: transform var(--duration-fast, 150ms);
    }

    .switch[data-state='checked'] .switch-thumb {
        transform: translateX(calc(0.25rem * 3));
    }
</style>
