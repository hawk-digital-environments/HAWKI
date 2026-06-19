<!--
  @component Visual switch indicator. Use bindable parent state to control checked.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLSpanElement> {
        /** Whether the switch is visually checked. */
        checked?: boolean;
        /** When true, the switch uses the disabled appearance. */
        disabled?: boolean;
        /**
         * Render as a non-interactive visual only (no role/tabindex/handler).
         * Use when a parent element is the actual control, so focus and keyboard
         * handling aren't duplicated on a nested element.
         */
        presentational?: boolean;
    }

    let {checked = $bindable(false), disabled = false, presentational = false, class: className, ...restProps}: Props = $props();

    const toggle = () => {
        if (disabled || presentational) return;
        checked = !checked;
    };
</script>

<span
    {...restProps}
    class={`switch${className ? ` ${className}` : ''}`}
    data-state={checked ? 'checked' : 'unchecked'}
    data-inactive={!checked && !disabled ? '' : undefined}
    data-disabled={disabled ? '' : undefined}
    role="checkbox"
    tabindex={presentational || disabled ? undefined : 0}
    aria-checked={checked ? 'true' : 'false'}
    aria-hidden={presentational ? 'true' : undefined}
    onclick={presentational ? undefined : toggle}
>
    <span class="switch-thumb"></span>
</span>

<style>
    .switch {
        display: inline-flex;
        cursor: pointer;
        height: calc(0.25rem * 4);
        width: calc(0.25rem * 7);
        flex-shrink: 0;
        align-items: center;
        border-radius: var(--corner-full, 999px);
        background-color: color-mix(in oklch, var(--color-text-muted) 35%, var(--color-bg));
        padding: 1px;
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
        box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 0.18));
        transition: transform var(--duration-fast, 150ms);
    }

    .switch[data-state='checked'] .switch-thumb {
        transform: translateX(calc(0.25rem * 3));
    }
</style>
