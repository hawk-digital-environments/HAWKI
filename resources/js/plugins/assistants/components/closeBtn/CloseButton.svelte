<script lang="ts">

    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';

    let {
        size = 'small',
        onClick,
        icon: Icon = Cancel01Icon
    } = $props<{
        size?: "small" | "medium" | "large",
        onClick: () => void,
        icon?: IconComponent
    }>();
</script>

<button
        type="button"
        aria-label="Entfernen"
        class="close-btn"
        class:sizeSmall={size === "small"}
        class:sizeMedium={size === "medium"}
        class:sizeLarge={size === "large"}
        onclick={() => onClick()}
><Icon size="1em" /></button>

<style>
    button {
        position: relative;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: none;
        background: transparent;
        color: inherit;
        cursor: pointer;
        border-radius: var(--corner-full);
        opacity: 0.7;
        transition:
            opacity var(--transition-fast),
            background-color var(--transition-fast);
    }
    button:hover {
        opacity: 1;
        background-color: color-mix(in oklch, currentColor 15%, transparent);
    }
    .sizeSmall {
        width: 1.125rem;
        height: 1.125rem;
        font-size: 0.9rem;
    }
    .sizeMedium {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 1.5rem;
        opacity: .5;
        transition: opacity var(--duration-medium);
    }
    .sizeMedium:hover {
        opacity: 1;
    }
    .sizeLarge {
        width: 2rem;
        height: 2rem;
        font-size: 2rem;
    }
</style>
