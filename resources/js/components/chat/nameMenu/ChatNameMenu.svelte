<script lang="ts">

    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import {ChevronDown, type LucideProps, Pencil, Trash2} from '@lucide/svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import type {Component, ComponentProps, Snippet} from 'svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {__} from '$lib/utils/translator.js';

    const toastContext = useToastContext();

    interface Props extends HTMLAttributes<HTMLDivElement> {
        chatName: string;
        chatSlug: string;
        onChatNameChange?: (slug: string, newName: string) => void;
        triggerIcon?: Component<LucideProps>;
        allowRename?: boolean;
        allowDelete?: boolean;
        chatNameClickRenames?: boolean;
        /** If true, "delete" is treated as "leaving a room" (including modified text and confirmation) */
        deleteIsLeave?: boolean;
        onDelete?: (slug: string) => void;
        moreItems?: Snippet;
        /**
         * Additional props forwarded to the ButtonWithTooltip that triggers the menu.
         * Be careful with this, overriding certain props (like `tooltip`) can break the component's functionality or accessibility.
         */
        buttonProps?: Partial<ComponentProps<typeof ButtonWithTooltip>>;
        isRenaming?: boolean;
        isDeleting?: boolean;
    }

    let {
        chatName = $bindable(''),
        chatSlug,
        onChatNameChange = (slug, newName) => oldUiBridge.triggerRenameChat(slug, newName),
        triggerIcon = ChevronDown,
        allowRename = true,
        allowDelete = true,
        chatNameClickRenames = false,
        deleteIsLeave = false,
        onDelete = (slug) => oldUiBridge.triggerDeleteChat(slug),
        moreItems,
        buttonProps,
        isDeleting = $bindable(false),
        isRenaming = $bindable(false),
        ...restProps
    }: Props = $props();

    let renameInput: HTMLInputElement | null = $state(null);
    let renameHasIssue = $state(false);

    function dispatchRename(newName: string) {
        if (!chatSlug || !isRenaming) {
            return;
        }
        if (newName === chatName) {
            isRenaming = false;
            return;
        }
        onChatNameChange(chatSlug, newName);
        isRenaming = false;
    }

    function onRenameKeyDown(event: KeyboardEvent) {
        if (event.key === ' ') {
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
        if (event.key === 'Enter') {
            event.stopPropagation();
            const newName = (event.target as HTMLInputElement).value;
            if (!newName.trim()) {
                renameHasIssue = true;
                toastContext.error(__('chat.nameMenu.emptyNameError'));
                return;
            }
            dispatchRename((event.target as HTMLInputElement).value);
        }
        if (event.key === 'Escape') {
            isRenaming = false;
        }
    }

    $effect(() => {
        if (allowRename && isRenaming && renameInput) {
            setTimeout(() => {
                if(!renameInput){
                    return;
                }
                renameInput.value = chatName; // Reset input value to current chat name when renaming starts, in case it was changed while not focused
                renameInput.focus();
                renameInput.select();
            });
        }
    });

    const deleteActionText = $derived.by(() => {
        if (deleteIsLeave) {
            return __('chat.nameMenu.leaveAction');
        }
        return __('chat.nameMenu.deleteAction');
    });

    const deleteConfirmDescription = $derived.by(() => {
        if (deleteIsLeave) {
            return __('chat.nameMenu.leaveConfirmDescription');
        }
        return __('chat.nameMenu.deleteConfirmDescription');
    });

    const deleteConfirmTitle = $derived.by(() => {
        if (deleteIsLeave) {
            return __('chat.nameMenu.leaveConfirmTitle', {name: chatName});
        }
        return __('chat.nameMenu.deleteConfirmTitle', {name: chatName});
    });
</script>

<ConfirmDialog
    bind:open={isDeleting}
    title={deleteConfirmTitle}
    description={deleteConfirmDescription}
    onConfirm={() => onDelete(chatSlug) }
/>

<div {...mergeProps(
    {class: 'chat-name-menu'},
    restProps
)}>
    {#if allowRename && isRenaming}
        <!-- Stop clicks and focus events from bubbling to parent elements (e.g. sidebar buttons) while renaming-->
        <!-- svelte-ignore a11y_autofocus -->
        <input
            bind:this={renameInput}
            onclick={(e) => e.preventDefault()}
            onblur={(e) => dispatchRename((e.target as HTMLInputElement).value)}
            onkeydown={onRenameKeyDown}
            autofocus
            aria-label={__('chat.nameMenu.newNameAriaLabel')}
            value={chatName}
            class={[
                "chat-name-input",
                renameHasIssue ? 'has-issue' : ''
            ]}
        />
    {:else}
        {#if chatNameClickRenames}
            <button class="chat-name click-to-rename" onclick={() => isRenaming = true}>
                {chatName}
            </button>
        {:else}
            <span class="chat-name">{chatName}</span>
        {/if}
        <DropdownMenu>
            {#snippet trigger({props})}
                <ButtonWithTooltip {...mergeProps(
                    {
                        variant: 'ghost',
                        size: 'sm',
                        iconLeft: triggerIcon,
                        tooltip: __('chat.nameMenu.actionsTooltip'),
                        highlight: props['data-state'],
                    },
                    props,
                    buttonProps as any,
                )}/>
            {/snippet}
            {#if allowRename && !!chatSlug}
                <DropdownMenuItem onclick={() => isRenaming = true}>
                    <span><Pencil size="12"/> {__('chat.nameMenu.rename')}</span>
                </DropdownMenuItem>
            {/if}
            {@render moreItems?.()}
            <DropdownMenuSeparator/>
            {#if allowDelete && !!chatSlug}
                <DropdownMenuItem onclick={() => isDeleting = true}>
                    <span class="danger">
                        <Trash2 size="12"/> {deleteActionText}
                    </span>
                </DropdownMenuItem>
            {/if}
        </DropdownMenu>
    {/if}
</div>

<style>
    .danger {
        color: var(--color-error)
    }

    .chat-name-menu {
        width: 100%;
        display: flex;
        align-items: center;
        gap: var(--space-1);
        flex-shrink: 1;
    }

    .chat-name-input {
        padding: var(--space-0_5);
        height: auto;
        min-height: unset;
        background: transparent;

        &.has-issue {
            outline-color: var(--color-error);
        }
    }

    .chat-name {
        /* Reset button styles */
        padding: 0;
        height: auto;
        min-height: unset;
        background: transparent;
        font: inherit;
        color: inherit;
        border: none;
        cursor: inherit;
        /* Text truncation */
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;

        &.click-to-rename {
            cursor: text;
        }
    }
</style>
