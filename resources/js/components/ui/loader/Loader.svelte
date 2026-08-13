<!--
  @component Swaps its `children` for a loading spinner while `active` is true. Use to replace
  content in place (e.g. a button label, a panel body) rather than overlaying it.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Shows the spinner instead of `children` while true. */
        active: boolean;
        /** Content rendered when not loading. */
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
