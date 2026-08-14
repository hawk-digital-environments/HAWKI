<script lang="ts">

    import CloseButton from "$plugins/assistants/components/closeBtn/CloseButton.svelte";
    import type {IconComponent} from '$lib/components/ui/icons';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';

    let {
        label,
        description,
        icon,
        highlighted = false,
        onDelete,
    } = $props<{
        label: string,
        description?: string,
        icon?: IconComponent,
        highlighted?: boolean,
        onDelete?: () => void,
    }>();

</script>

<div class="item">
    <div class="content"
        class:highlight={highlighted}
    >
        {#if icon}
            {@const IconCmp = icon}
            <div class="icon-wrapper">
                <span class="icon"><IconCmp size="1em" /></span>
            </div>
        {/if}
        <div class="text-wrapper">
            <p class="label">{label}</p>
            {#if description}
                <p class="description">{description}</p>
            {/if}
        </div>
    </div>
    {#if onDelete}
        <div class="removeBtn">
            <CloseButton
                icon={Delete02Icon}
                size="medium"
                onClick={() => onDelete?.()}/>
        </div>
    {/if}
</div>

<style>
    .item{
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 1rem;
        width: 100%;
        align-items: center;
        transition: all var(--duration-medium);
    }
    .removeBtn{
        width: 2rem;
    }
    .content{
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 1rem;
        text-align: left;
        border-radius: var(--corner-md);
        border: var(--border);
        padding: .5rem 1rem;
    }
    .content.highlight{
        animation: highlight 200ms ease-in-out;
    }
    @keyframes highlight {
        0% { transform: translateX(0) }
        25% { transform: translateX(5px) }
        50% { transform: translateX(-5px) }
        75% { transform: translateX(5px) }
        100% { transform: translateX(0) }
    }

    .description{
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        margin: 0;
    }
    .icon-wrapper{
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        background-color: var(--color-accent-100);
        color: var(--color-accent-text);
        border-radius: var(--corner-sm);
    }
    .icon{
        font-size: var(--font-size-base);
    }
</style>
