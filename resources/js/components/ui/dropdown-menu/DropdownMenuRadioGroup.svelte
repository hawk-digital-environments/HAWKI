<!--
  @component Groups DropdownMenuRadioItem elements so only one can be selected at a time.
  Use bind:value for two-way state.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive} from 'bits-ui';
    import type {Snippet} from 'svelte';

    interface Props {
        /** The value of the currently selected radio item. Supports bind:value. */
        value?: string;
        /** Called when the selected value changes. */
        onValueChange?: (value: string) => void;
        /** DropdownMenuRadioItem elements. */
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
