<!--
  @component Visual switch indicator. Use bindable parent state to control checked.

  By default it is a standalone toggle: it renders a native `<button
  role="switch">`, so clicking/tapping it or pressing Space/Enter flips
  `checked` itself. Pass `aria-label`/`aria-labelledby` for its accessible
  name. Set `presentational` when the switch is nested inside a larger
  interactive element (e.g. a menu item) that already owns focus/click
  handling — this renders an inert `<span aria-hidden>` so behavior isn't
  duplicated on two elements.

  @example Standalone toggle:
  ```svelte
  <Switch bind:checked={notificationsEnabled}/>
  ```

  @example Presentational, driven by a parent menu item's own click handler:
  ```svelte
  <button onclick={toggleFromIndicator}>
      <Switch checked={isChecked} presentational/>
  </button>
  ```
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLElement> {
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

{#if presentational}
    <span
        {...restProps}
        class={`switch${className ? ` ${className}` : ''}`}
        data-state={checked ? 'checked' : 'unchecked'}
        data-inactive={!checked && !disabled ? '' : undefined}
        data-disabled={disabled ? '' : undefined}
        aria-hidden="true"
    >
        <span class="switch-thumb"></span>
    </span>
{:else}
    <button
        {...restProps}
        type="button"
        role="switch"
        class={`switch${className ? ` ${className}` : ''}`}
        data-state={checked ? 'checked' : 'unchecked'}
        data-inactive={!checked && !disabled ? '' : undefined}
        data-disabled={disabled ? '' : undefined}
        aria-checked={checked}
        {disabled}
        onclick={toggle}
    >
        <span class="switch-thumb"></span>
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
        border-radius: var(--corner-full, 999px);
        background-color: color-mix(in oklch, var(--color-text-muted) 35%, var(--color-bg));
        padding: 1px;
        border: none;
        transition: background-color var(--duration-fast, 150ms);
    }

    .switch:focus-visible {
        outline: 2px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: 2px;
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
