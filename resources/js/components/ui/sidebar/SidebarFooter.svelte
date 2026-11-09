<!--
  @component Bottom-pinned slot at the foot of the sidebar. Typically holds the
  profile row. Sits outside the scrollable items list so it stays visible.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Footer content (e.g. the profile row). */
        children: Snippet;
    }

    const {children, class: className, ...restProps}: Props = $props();
</script>

<div {...restProps} class={["sidebar-footer", className]}>
    {@render children()}
</div>

<style>
    .sidebar-footer {
        margin-top: auto;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        /* The same as the panel's padding. */
        gap: var(--space-4);
        padding-top: var(--space-4);
    }
</style>
