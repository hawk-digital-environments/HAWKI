<!--
  @component The line a `DropdownMenu` shows in place of its items when there are none to
  show — a search that matched nothing, a list that hasn't got anything in it yet. Quiet and
  centred, so an empty menu reads as an answer rather than as a menu that failed to load.

  ```svelte
  {#if shown.length === 0}
      <DropdownMenuEmpty>{__('chat.composer.toolMenu.noResults')}</DropdownMenuEmpty>
  {:else}
      …rows…
  {/if}
  ```
-->
<script lang="ts">
    import {mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';

    interface Props extends HTMLAttributes<HTMLParagraphElement> {
        /** What to say about the emptiness. */
        children?: Snippet;
    }

    const {children, ...restProps}: Props = $props();
</script>

<p {...mergeProps({class: 'dropdown-empty'}, restProps)}>
    {@render children?.()}
</p>

<style>
    .dropdown-empty {
        padding: var(--space-3);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        text-align: center;
    }
</style>
