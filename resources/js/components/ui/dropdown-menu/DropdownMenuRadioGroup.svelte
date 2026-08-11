<!--
  @component Groups `DropdownMenuRadioItem` children inside a `DropdownMenu`
  so only one of them can be selected at a time. Use `bind:value` for
  two-way state, or `value` + `onValueChange` for externally-controlled
  state. `value` must match one of the child items' `value` prop.

  ```svelte
  <DropdownMenu trigger="Sort by">
      <DropdownMenuRadioGroup bind:value={sortOrder}>
          <DropdownMenuRadioItem value="name">{__('menu.sortByName')}</DropdownMenuRadioItem>
          <DropdownMenuRadioItem value="date">{__('menu.sortByDate')}</DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive} from 'bits-ui';
    import type {Snippet} from 'svelte';

    interface Props {
        /** The value of the currently selected radio item. Supports bind:value. */
        value?: string;
        /** Called when the selected value changes. */
        onValueChange?: (value: string) => void;
        /** `DropdownMenuRadioItem` elements. */
        children?: Snippet;
    }

    let {
        value = $bindable(''),
        onValueChange,
        children
    }: Props = $props();
</script>

<DropdownMenuPrimitive.RadioGroup bind:value {onValueChange}>
    {@render children?.()}
</DropdownMenuPrimitive.RadioGroup>
