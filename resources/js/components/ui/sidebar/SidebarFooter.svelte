<!--
  @component Bottom-pinned slot at the foot of the sidebar. Typically holds the
  profile row. Sits outside the scrollable items list so it stays visible.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Footer content (e.g. the profile row). */
        children: Snippet;
    }

    const {children}: Props = $props();
</script>

<div class="sidebar-footer">
    {@render children()}
</div>

<style>
    .sidebar-footer {
        margin-top: auto;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        /* Footer rows are separate groups (an action, the profile), so they take
           the sidebar's group gap — `--space-3`, the same separation the header
           leaves above the nav rows — rather than the tight within-group row
           gap. The padding above the footer is that same group gap, so the nav
           rows end at the same distance from the first footer row as every
           other group boundary. */
        gap: var(--space-3);
        padding-top: var(--space-3);
    }
</style>
