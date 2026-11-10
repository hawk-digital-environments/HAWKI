<script lang="ts">
    let {
        label,
        isActive = false,
        onchange
    } = $props<{
        label: string;
        isActive?: boolean;
        onchange: () => void;
    }>();
</script>

<button
    class="category-btn"
    class:isActive
    onclick={onchange}
>
    {label}
</button>

<style>
    /* Filled pill: no outline at rest, a deeper fill on hover, and the active
       chip inverts to a solid ink pill so the selection reads at a glance. */
    button {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 2.25rem;
        width: fit-content;
        min-width: fit-content;
        padding: 0 var(--space-4);
        color: var(--color-text-muted);
        background-color: var(--color-surface-light);
        font-size: var(--font-size-nav);
        font-weight: var(--font-weight-medium);
        line-height: 1;
        border: none;
        border-radius: var(--corner-full);
        overflow: hidden;
        text-wrap: nowrap;
        white-space: nowrap;
        cursor: pointer;
        /* Release springs back past rest and settles. */
        transition:
            background-color 150ms ease-out,
            color 150ms ease-out,
            scale 450ms cubic-bezier(0.34, 3.85, 0.64, 1);
    }
    button:hover {
        color: var(--color-text);
        background-color: color-mix(in oklch, var(--color-surface-light), var(--color-text) 7%);
    }
    /* Press: a quick, direct squeeze. */
    button:active {
        scale: 0.94;
        transition:
            background-color 150ms ease-out,
            color 150ms ease-out,
            scale 120ms cubic-bezier(0.22, 1, 0.36, 1);
    }
    button.isActive,
    button.isActive:hover {
        color: var(--color-surface-raised);
        background-color: var(--color-text);
    }

    @media (prefers-reduced-motion: reduce) {
        button,
        button:active {
            scale: none;
        }
    }
</style>
