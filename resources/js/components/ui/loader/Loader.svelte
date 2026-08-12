<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        active: boolean;
        children: Snippet;
    }

    const {children, active}: Props = $props();
</script>

{#if active}
    <div class="loader">
        <div class="spinner">Loading!</div>
    </div>
{:else}
    {@render children()}
{/if}
