<script lang="ts">
    let {
        label,
        isActive = false,
        onchange,
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
    button{
        display: flex;
        align-items: center;
        justify-content: center;
        height: 2rem;
        width: fit-content;
        min-width: fit-content;
        padding: 0 var(--space-4);
        color: var(--color-text);
        background-color: transparent;
        font-size: var(--font-size-nav);
        line-height: 1;
        border: var(--border);
        border-radius: var(--corner-full);
        overflow: hidden;
        text-wrap: nowrap;
        white-space: nowrap;
        cursor: pointer;
        opacity: 0.9;
        transition:
            background-color var(--transition-fast),
            border-color var(--transition-fast),
            color var(--transition-fast),
            opacity var(--transition-fast);
    }
    button:hover{
        opacity: 1;
        background-color: var(--color-hover);
        border-color: var(--color-hover);
    }
    button.isActive{
        color: var(--color-accent-text);
        background-color: var(--color-accent-100);
        border-color: transparent;
        opacity: 1;
    }
</style>