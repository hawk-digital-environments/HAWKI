<script lang="ts">
    import type {Snippet} from "svelte";

    let {
        label,
        render = 'block',
        children,
        actions,
    } = $props<{
        label: string;
        render?: 'block' | 'inline';
        children: Snippet;
        /** Optional controls rendered at the header row's right edge (e.g. a manage-files menu). */
        actions?: Snippet;
    }>();

</script>
<div class="input-container"
     class:renderBlock={render === 'block'}
     class:renderInline={render === 'inline'}
>
    {#if label || actions}
        <div class="header">
            {#if label}
                <p class="label">{label}</p>
            {/if}
            {#if actions}
                {@render actions()}
            {/if}
        </div>
    {/if}
    <div class="items-container">
        {@render children() }
    </div>
</div>

<style>
    .header{
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    .items-container{
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
</style>