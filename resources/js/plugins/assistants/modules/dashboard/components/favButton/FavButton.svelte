<script lang="ts">

    import FavouriteIcon from '$lib/components/ui/icons/iconset/FavouriteIcon.svelte';
    import {ActionIcon} from '$lib/components/ui/icons';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';

    const {__} = useTranslator();

    let {
        id,
        color = 'var(--color-text)',
        background = 'oklch(100% 0 0 / 0.25)',
        isActive = false,
        variant = 'frosted',
        onchange,
    } = $props <{
        id?: string;
        color?: string;
        background?: string;
        isActive: boolean;
        /** `frosted` floats over imagery (cards); `stroke` is an outline button for button rows. */
        variant?: 'frosted' | 'stroke';
        onchange?: (value: boolean) => void;
    }>();

    // @todo: wait for the server confirmation before changing isActive;
    function onclick(e: MouseEvent) {
        e.preventDefault();
        e.stopPropagation();
        isActive = !isActive;
        onchange?.(isActive);
    }


</script>

{#if variant === 'stroke'}
    <ButtonWithTooltip
            {id}
            variant="stroke"
            iconLeft={FavouriteIcon}
            tooltip={__('assistants.detail.favourite_aria')}
            aria-pressed={isActive}
            class={isActive ? 'fav-btn--active' : undefined}
            {onclick}
    />
{:else}
    <ActionIcon
            {id}
            icon={FavouriteIcon}
            label={__('assistants.detail.favourite_aria')}
            variant="frosted"
            active={isActive}
            style="--action-icon-bg: {background}; --action-icon-color: {color}"
            {onclick}
    />
{/if}

<style>
    :global(.btn.fav-btn--active .btnIcon) {
        fill: currentColor;
    }
</style>
