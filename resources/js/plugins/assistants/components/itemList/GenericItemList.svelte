<script lang="ts">
    import type {Snippet} from "svelte";

    let {
        label,
        render = 'block',
        children,
    } = $props<{
        label: string;
        render?: 'block' | 'inline';
        children: Snippet;
    }>();

</script>
<div class="input-container"
     class:renderBlock={render === 'block'}
     class:renderInline={render === 'inline'}
>
    {#if label}
        <p class="label">{label}</p>
    {/if}
    <div class="items-container">
        {@render children() }
    </div>
</div>

<style>
    .items-container{
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
</style>