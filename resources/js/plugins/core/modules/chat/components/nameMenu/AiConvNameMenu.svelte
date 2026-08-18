<!--
  @component Chat name menu specialised for AI conversations.

  Wraps `ChatNameMenu` and adds a single destructive "Delete" action gated
  behind a `ConfirmDialog`; on confirm it forwards to
  `oldUiBridge.triggerDeleteChat`. Forwards the rename surface (`name`,
  `slug`, `isRenaming`, …) straight through to `ChatNameMenu`. Use this for
  AI conversations that don't have the multi-user room actions.

  @example
  <AiConvNameMenu
      bind:name={conv.name}
      bind:isRenaming={renaming}
      slug={conv.slug}
  />
-->
<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import {ConfirmDialog, DropdownMenuItem, DropdownMenuSeparator} from '@hawk-hhg/hawki-svelte-components';
    import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import ChatNameMenu from '$plugins/core/modules/chat/components/nameMenu/ChatNameMenu.svelte';

    const {__} = useTranslator();

    type Props = {} & Pick<ComponentProps<typeof ChatNameMenu>,
        'name' | 'nameClickRenames' | 'slug' | 'allowRename' | 'isRenaming' |
        'class' | 'buttonProps' | 'block' | 'triggerIcon'>;

    let {
        name,
        slug,
        isRenaming = $bindable(false),
        ...restProps
    }: Props = $props();

    let deleteConfirmOpen = $state(false);
</script>
<ConfirmDialog
    bind:open={deleteConfirmOpen}
    title={__('chat.nameMenu.deleteConfirmTitle', {name: name})}
    onConfirm={() => slug && oldUiBridge.triggerDeleteChat(slug)}
/>
<ChatNameMenu
    bind:isRenaming={isRenaming}
    name={name ?? ''}
    slug={slug ?? ''}
    {...restProps}
>
    {#if !!slug}
        <DropdownMenuSeparator/>

        <DropdownMenuItem
            variant="destructive"
            onclick={() => deleteConfirmOpen = true}>
            {__('chat.nameMenu.deleteAction')}
        </DropdownMenuItem>
    {/if}
</ChatNameMenu>
