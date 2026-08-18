<script lang="ts">

    import HelpCircleIcon from '$lib/components/ui/icons/iconset/HelpCircleIcon.svelte';
    let{
        visibility,
        onClick,
    }= $props <{
        visibility: boolean,
        onClick?: () => void;
    }>();

</script>

<button class="help-button" type="button"
        class:hidden = {!visibility}
        onclick={()=> onClick?.()}
>
    <span class="icon"><HelpCircleIcon size="1em" /></span>
</button>

<style>
    .help-button{
        position: absolute;
        display: flex;
        bottom: var(--space-4);
        right: var(--space-4);
        width: 2.5rem;
        height: 2.5rem;
        align-items: center;
        justify-content: center;
        border-radius: var(--corner-full);
        border: var(--border);
        background-color: var(--color-surface-raised);
        color: var(--color-accent-text);
        box-shadow: var(--elevation-1);
        cursor: pointer;
        transition:
            background-color var(--duration-fast),
            color var(--duration-fast),
            transform var(--duration-fast);
    }
    .help-button:hover{
        background-color: var(--color-hover);
        color: var(--color-text);
        transform: scale(1.05);
    }
    .help-button.hidden{
        display: none;
    }
    .icon{
        font-size: var(--font-size-lg);
    }
</style>
