<!--
  @component Scrollable container for the nav tree. A MenuList in sidebar clothing:
  the proximity hover and the two sliding highlights (active selection +
  nearest-hover) come from there, this only supplies how far a child row's
  highlight is indented.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';

    interface Props {
        /** The nav rows (SidebarItem instances). */
        children: Snippet;
        /** Suppress both sliding highlights (active selection + proximity hover),
            e.g. while the nav is sliding through a drill-down transition. */
        disabled?: boolean;
    }

    const {children, disabled = false}: Props = $props();
</script>

<!-- Child rows are indented to align with their parent's label; their highlight
     follows suit. -->
<MenuList {disabled} style="--list-inset: calc(var(--space-3) + var(--nav-icon-size));">
    {@render children()}
</MenuList>
