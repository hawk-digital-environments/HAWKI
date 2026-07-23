<!--
  @component Groups related dropdown menu items together with optional shared semantics.
  Typically used alongside DropdownMenuLabel to label the group.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The items that belong to this group. */
        children?: Snippet;
    }

    const {children, class: className, ...restProps}: Props = $props();
</script>

<DropdownMenuPrimitive.Group>
    {#snippet child({props})}
        <div {...mergeProps({class: className ?? ''}, restProps, props)}>
            {@render children?.()}
        </div>
    {/snippet}
</DropdownMenuPrimitive.Group>
