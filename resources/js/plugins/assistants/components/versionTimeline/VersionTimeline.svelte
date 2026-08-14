<script lang="ts">
    import type {Snippet} from "svelte";

    let{
        children
    } = $props<{
        children: Snippet;
    }>();

</script>


<div class="input-container renderBlock">
    <div class="label">Änderungsverlauf</div>

    <div class="version-list">
        {@render children()}
    </div>
</div>


<style>
    .version-list{
        margin-top: 1rem;
    }
</style>