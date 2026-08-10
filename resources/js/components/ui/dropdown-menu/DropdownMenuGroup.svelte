<!--
  @component Groups related `DropdownMenuItem`s (or other item components)
  inside a `DropdownMenu` for shared ARIA semantics (`role="group"`).
  Typically paired with a `DropdownMenuLabel` as the group's first child so
  the group has a visible heading; use `DropdownMenuSeparator` between
  groups.

  ```svelte
  <DropdownMenu trigger="Actions">
      <DropdownMenuGroup>
          <DropdownMenuLabel>{__('menu.viewLabel')}</DropdownMenuLabel>
          <DropdownMenuItem onSelect={openView}>{__('menu.view')}</DropdownMenuItem>
      </DropdownMenuGroup>
      <DropdownMenuSeparator />
      <DropdownMenuGroup>
          <DropdownMenuLabel>{__('menu.dangerLabel')}</DropdownMenuLabel>
          <DropdownMenuItem variant="destructive" onSelect={remove}>{__('menu.delete')}</DropdownMenuItem>
      </DropdownMenuGroup>
  </DropdownMenu>
  ```
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
