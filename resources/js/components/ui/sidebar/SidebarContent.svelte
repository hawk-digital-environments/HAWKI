<!--
  @component Main content area. Fills the grid's `main` column and renders the
  page content next to the sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Page content. */
        children: Snippet;
    }

    const {children}: Props = $props();
</script>

<main class="content">
    {@render children()}
</main>

<style>
    .content {
        position: relative;
        grid-area: main;
        min-width: 0;
        overflow: hidden;
        background: var(--panel-bg);
    }
</style>
