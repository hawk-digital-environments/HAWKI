<script lang="ts">

    import CloseButton from "$plugins/assistants/components/closeBtn/CloseButton.svelte";
    import {StatusIcon} from '$lib/components/ui/icons';
    import type {IconComponent} from '$lib/components/ui/icons';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    import type {Snippet} from 'svelte';

    let {
        label,
        description,
        icon,
        highlighted = false,
        onDelete,
        trailing,
    } = $props<{
        label: string,
        description?: string,
        icon?: IconComponent,
        highlighted?: boolean,
        onDelete?: () => void,
        /** Optional element rendered at the row's right edge, before the delete button (e.g. a status indicator). */
        trailing?: Snippet,
    }>();

</script>

<div class="item">
    <div class="content"
        class:highlight={highlighted}
    >
        {#if icon}
            {@const IconCmp = icon}
            <StatusIcon icon={IconCmp}/>
        {/if}
        <div class="text-wrapper">
            <p class="label">{label}</p>
            {#if description}
                <p class="description">{description}</p>
            {/if}
        </div>
        {#if trailing}
            <div class="trailing">
                {@render trailing()}
            </div>
        {/if}
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
        display: flex;
        align-items: center;
        justify-content: center;
    }
    /* Same 2rem centered slot as .icon-wrapper, so leading icon, trailing
       element and delete button all share one centerline. */
    .trailing{
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        margin-left: auto;
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
</style>
