<!--
  @component Builder entry wrapper. Renders inside the global AppLayout content
  area. The creation-method choice is presented by the page itself
  (entry/+page.svelte), so this no longer adds its own secondary sidebar.
-->
<script lang="ts">
    let { children } = $props();
</script>

<div class="builder-entry">
    {@render children()}
</div>

<style>
    .builder-entry {
        height: 100%;
        overflow-y: auto;
    }
</style>
