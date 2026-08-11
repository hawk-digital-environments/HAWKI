<!--
  @component A thin horizontal rule that visually separates groups of menu
  items inside a `DropdownMenu`. Purely presentational — takes no props of
  its own beyond standard HTML attributes.

  ```svelte
  <DropdownMenu trigger="Actions">
      <DropdownMenuItem onSelect={rename}>{__('menu.rename')}</DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem variant="destructive" onSelect={remove}>{__('menu.delete')}</DropdownMenuItem>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {}

    const {class: className, ...restProps}: Props = $props();
</script>

<DropdownMenuPrimitive.Separator>
    {#snippet child({props})}
        <div
            role="separator"
            aria-orientation="horizontal"
            {...mergeProps({class: `dropdown-separator${className ? ` ${className}` : ''}`}, restProps, props)}
        ></div>
    {/snippet}
</DropdownMenuPrimitive.Separator>

<style>
    .dropdown-separator {
        margin-inline: calc(-1 * var(--space-1, 0.25rem));
        margin-block: var(--space-1, 0.25rem);
        height: 1px;
        background-color: var(--color-border);
    }
</style>
