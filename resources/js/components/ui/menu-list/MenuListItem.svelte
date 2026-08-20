<!--
  @component Registers a row with the enclosing MenuList so the sliding highlights
  can track it. It renders no markup of its own: the row element is the
  consumer's, and gets wired up through the attachment handed to the children
  snippet.

      <MenuListItem active={selected} inset={nested}>
          {#snippet children({attach})}
              <button {@attach attach}>…</button>
          {/snippet}
      </MenuListItem>

  Rows used outside a List simply render — the attachment is inert, so the same
  component works standalone (e.g. in a footer), just without a highlight
  behind it.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import {useMenuList} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';

    interface Props {
        /** Marks the row as the list's current selection. */
        active?: boolean;
        /** Indents the row's highlight by the list's `--list-inset`. */
        inset?: boolean;
        /** The row itself; receives the attachment that registers its element. */
        children: Snippet<[{attach: Attachment<HTMLElement>}]>;
    }

    const {active = false, inset = false, children}: Props = $props();

    const list = useMenuList();
    let index = $state(-1);

    const attach: Attachment<HTMLElement> = (element) => {
        if (!list) return;
        const assigned = list.register(element);
        index = assigned;
        return () => {
            list.unregister(assigned);
            index = -1;
        };
    };

    $effect(() => {
        if (list && index >= 0) list.setState(index, {active, inset});
    });
</script>

{@render children({attach})}
