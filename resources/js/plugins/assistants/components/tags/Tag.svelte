<script lang="ts">
    import CloseButton from "$plugins/assistants/components/closeBtn/CloseButton.svelte";

    let {
        value,
        highlighted = false,
        onDelete,
    } = $props<{
        value: string,
        highlighted?: boolean,
        onDelete?: () => void,
    }>();

</script>

<div class="tag"
     class:highlight={highlighted}
>
    <div class="content">
        <p class="label label-xs">{value}</p>
    </div>
    {#if onDelete}
        <CloseButton onClick={() => {onDelete()}}/>
    {/if}
</div>
<style>
    .tag{
        display: flex;
        flex-direction: row;
        gap: var(--space-1);
        height: 2.25rem;
        width: max-content;
        justify-content: space-between;
        padding: 0 var(--space-1_5) 0 var(--space-3);
        align-items: center;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        border-radius: var(--corner-full);
        background-color: var(--color-accent-100);
        color: var(--color-accent-text);
        transition: background-color var(--duration-fast);
    }
    .tag:hover{
        background-color: var(--color-accent-200);
    }
    .tag.highlight{
        animation: highlight 200ms ease-in-out;
    }
    @keyframes highlight {
        0% { transform: translateX(0) }
        25% { transform: translateX(5px) }
        50% { transform: translateX(-5px) }
        75% { transform: translateX(5px) }
        100% { transform: translateX(0) }
    }

    .content{
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: .5rem;
    }
</style>
