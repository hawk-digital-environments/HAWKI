<script lang="ts">

    import FavouriteIcon from '$lib/components/ui/icons/iconset/FavouriteIcon.svelte';
    import {ActionIcon} from '$lib/components/ui/icons';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';

    const {__} = useTranslator();

    let {
        id,
        color = 'var(--color-text)',
        background = 'oklch(100% 0 0 / 0.25)',
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

<ActionIcon
        {id}
        icon={FavouriteIcon}
        label={__('assistants.detail.favourite_aria')}
        variant="frosted"
        active={isActive}
        style="--action-icon-bg: {background}; --action-icon-color: {color}"
        onclick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onclick();
        }}
/>
