<script lang="ts">

    import FavouriteIcon from '$lib/components/ui/icons/iconset/FavouriteIcon.svelte';

    let {
        id,
        color = 'var(--color-text)',
        background = 'oklch(100% 0 0 / 0.25);',
        isActive = false,
        onchange,
    } = $props <{
        id?: string;
        color?: string;
        background?: string;
        isActive: boolean;
        onchange?: (value: boolean) => void;
    }>();

    // @todo: wait for the server confirmation before changing isActive;
    function onclick() {
        isActive = !isActive;
        onchange?.(isActive);
    }


</script>

<button class="favButton"
        class:isActive
        style:background={background}
        onclick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onclick();
        }}
>
    <span class="icon"
        style:color={color}
    ><FavouriteIcon size="1em" /></span>
</button>


<style>
    .favButton {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: none;
        /*background-color: oklch(100% 0 0 / 0.25);*/
        backdrop-filter: blur(6px);
        border-radius: var(--corner-full);
        cursor: pointer;
        transition:
            background-color var(--duration-fast),
            transform var(--duration-fast);
    }
    .icon{
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-lg);
    }
    .icon :global(svg) {
        display: block;
    }
    .favButton:hover {
        background-color: oklch(100% 0 0 / 0.4);
        transform: scale(1.06);
    }
    .favButton.isActive .icon :global(svg) {
        fill: currentColor;
    }

</style>
