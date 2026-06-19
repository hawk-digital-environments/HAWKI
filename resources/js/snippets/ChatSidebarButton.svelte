<script lang="ts">

    import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import ChatNameMenu from '$lib/components/chat/nameMenu/ChatNameMenu.svelte';
    import {Ellipsis} from '@lucide/svelte';
    import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';
    import type {HTMLSvelteSnippetElement} from '$lib/svelteSnippetLoader.js';

    interface Props {
        slug?: string;
        roomName?: string;
        context: ComposerContextType;
        root: HTMLSvelteSnippetElement;
    }

    const {
        slug,
        roomName,
        root
    }: Props = $props();

    // Binding renaming allows us to prevent the click event from propagating and opening
    // the conversation when the user is trying to rename it. (This includes hitting the space or enter key to confirm the rename, which would otherwise also trigger the conversation to open.)
    let isRenaming = $state(false);

    $effect(() => {
        if (slug && oldUiMessageHistory.conversationSlug === slug) {
            if (roomName !== oldUiMessageHistory.conversationName) {
                root.setProps({roomName: oldUiMessageHistory.conversationName});
            }
        }
    });
</script>

<button
    class="sidebar-button btn-md selection-item"
    onclick={() => !isRenaming && slug && oldUiBridge.triggerOpenChat(slug)}>
    <ChatNameMenu
        bind:isRenaming={isRenaming}
        chatName={roomName ?? ''}
        chatSlug={slug ?? ''}
        chatNameClickRenames={false}
        class="chat-name-menu-block"
        triggerIcon={Ellipsis}
        buttonProps={{class: 'chat-name-menu-button'}}
    />
</button>

<style>
    :global(.chat-name-menu-button) {
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    .sidebar-button {
        display: flex;
        flex-direction: row;
        column-gap: .5rem;
        align-items: center;
        padding-left: 1rem;
        padding-right: .5rem;
        height: 2.5rem;
        border-radius: 5px;
        background-color: transparent;
        transition: background-color var(--transition-fast);
        cursor: pointer;
        width: 100%;
        text-align: left;
        outline-offset: -2px;

        &:hover {
            :global(.chat-name-menu-button) {
                opacity: 1;
            }
        }

        :global(#unread-msg-flag) {
            display: none;
        }

        &:global(:not(.selected):hover) {
            background-color: var(--panel-main);
            /* border: var(--border-stroke-thin); */
        }

        &:global(.selected) {
            background-color: var(--panel-main);
            border: var(--border-stroke-thin);
        }
    }

    :global(.active) .sidebar-button {
        background-color: var(--highlight-color);
    }

    :global(.chat-name-menu-block) {
        width: 100%;
        justify-content: space-between;
    }
</style>
