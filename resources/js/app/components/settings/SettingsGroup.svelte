<!--
  @component A run of related `SettingsRow`s, split by hairlines. Consecutive
  groups sit a little further apart than the rows inside them.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** The group's rows. */
        children: Snippet;
    }

    const {children}: Props = $props();
</script>

<div class="settings-group">
    {@render children()}
</div>

<style>
    .settings-group {
        display: flex;
        flex-direction: column;
    }
</style>
