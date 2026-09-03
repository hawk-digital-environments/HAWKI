<!--
  @component Header of a routed chat page: editable conversation name with
  rename/delete menu, export menu, optional skip-to-composer link and a
  fading backdrop over the scrolling message log.
-->
<script lang="ts">
    import ChatNameMenu from '$plugins/core/modules/chat/components/nameMenu/ChatNameMenu.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import ExportMenu from '$plugins/core/modules/chat/components/header/ExportMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import type {ConversationExportFormat} from '$plugins/core/modules/chat/utils/exportConversation.js';
    import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props extends HTMLAttributes<HTMLElement> {
        conversation: ChatConversation;
        onRename: (name: string) => void | Promise<void>;
        onDelete: () => void | Promise<void>;
        onExport: (format: ConversationExportFormat) => void | Promise<void>;
        generating?: boolean;
        /** When set, renders a visually hidden skip link that jumps focus past the message log into the composer. */
        onSkipToComposer?: () => void;
    }

    const {conversation, onRename, onDelete, onExport, generating = false, onSkipToComposer, class: className, ...restProps}: Props = $props();
    const {__} = useTranslator();
    let name = $derived(conversation.name);
    let deleteOpen = $state(false);
</script>

<PageHeaderBar {...restProps} srHeading={conversation.name} class={["u-print-hidden", className]}>
    <div class="name">
        <ChatNameMenu
            bind:name
            slug={conversation.slug}
            nameClickRenames
            onNameChange={(_, nextName) => onRename(nextName)}
        >
            <DropdownMenuItem variant="destructive" onclick={() => deleteOpen = true}>
                {__('chat.nameMenu.deleteAction')}
            </DropdownMenuItem>
        </ChatNameMenu>
    </div>
    <ExportMenu {onExport} />
    {#if onSkipToComposer}
        <button class="skip-to-composer" type="button" onclick={onSkipToComposer}>
            {__('chat.page.skipToComposer')}
        </button>
    {/if}
</PageHeaderBar>

<ConfirmDialog
    bind:open={deleteOpen}
    title={__('chat.nameMenu.deleteConfirmTitle', {name})}
    description={__('chat.nameMenu.deleteConfirmDescription')}
    onConfirm={onDelete}
/>

<style>
    .name {
        flex: 1;
        min-width: 0;
        max-width: 34rem;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    /* Like the app's skip link: invisible until keyboard focus, then a small
       pill below the header so it never shifts the header layout. */
    .skip-to-composer {
        position: absolute;
        top: calc(100% + var(--space-2));
        left: var(--space-5);
        /* Sits over the message log below the header — the bar's z-index
            keeps it above the log (see PageHeaderBar). */
        padding: var(--space-2) var(--space-3);
        border: none;
        border-radius: var(--corner-sm);
        background: var(--color-interactive);
        color: var(--color-on-interactive);
        font-weight: var(--font-weight-semibold);
        cursor: pointer;
    }

    .skip-to-composer:not(:focus) {
        width: 1px;
        height: 1px;
        padding: 0;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }
</style>
