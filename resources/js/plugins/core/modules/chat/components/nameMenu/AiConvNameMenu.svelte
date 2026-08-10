<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
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
