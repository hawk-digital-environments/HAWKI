<!--
  @component A non-interactive label displayed above a group of related menu items.
  Set inset=true to align with indented items (e.g. those with a leading icon or indicator).
-->
<script lang="ts">
    import {mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Ref to the root element. */
        ref?: HTMLDivElement | null;
        /** When true, adds left padding to align with indented menu items. */
        inset?: boolean;
        /** Label text or rich content. */
        children?: Snippet;
    }

    let {
        ref = $bindable(null),
        inset = false,
        children,
        class: className,
        ...restProps
    }: Props = $props();
</script>

<div
    bind:this={ref}
    data-slot="dropdown-menu-label"
    data-inset={inset || undefined}
    {...mergeProps({class: `dropdown-label${className ? ` ${className}` : ''}`}, restProps)}
>
    {@render children?.()}
</div>

<style>
    .dropdown-label {
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    .dropdown-label[data-inset] {
        padding-left: var(--space-8, calc(0.25rem * 8));
    }
</style>
