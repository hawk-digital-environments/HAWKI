<!--
  @component Scrollable container for the nav tree. A List in sidebar clothing:
  the proximity hover and the two sliding highlights (active selection +
  nearest-hover) come from there, this only supplies how far a child row's
  highlight is indented.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import List from '$lib/components/ui/list/List.svelte';

    interface Props {
        /** The nav rows (SidebarItem instances). */
        children: Snippet;
        /** Suppress both sliding highlights (active selection + proximity hover),
            e.g. while the nav is sliding through a drill-down transition. */
        paused?: boolean;
    }

    const {children, paused = false}: Props = $props();
</script>

<!-- Child rows are indented to align with their parent's label; their highlight
     follows suit. -->
<List {paused} style="--list-inset: calc(var(--space-3) + var(--nav-icon-size));">
    {@render children()}
</List>
