<!-- @component This is probably a temporary component, since it mixes concerns between chat and room messages
However, as we want to merge them both anyway, it's not worth the effort to split out the chat-specific parts into a separate component for now. -->
<script lang="ts">
    import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import type {HTMLSvelteSnippetElement} from '$lib/svelteSnippetLoader.js';
    import RoomNameMenu from '$lib/components/chat/nameMenu/RoomNameMenu.svelte';
    import AiConvNameMenu from '$lib/components/chat/nameMenu/AiConvNameMenu.svelte';
    import type {ComponentProps} from 'svelte';
    import EllipsisIcon from '$lib/components/ui/icons/iconset/EllipsisIcon.svelte';

    interface Props {
        /** The slug of the conversation this button represents. Clicking the button will open the conversation with this slug. */
        slug?: string;
        /** The name of the conversation to display. If not provided, the conversation name from oldUiMessageHistory will be used (if its slug matches the provided slug). */
        name?: string;
        /** The context in which the button is rendered, which can be used to determine whether certain features (like renaming) should be enabled. */
        context: ComposerContextType;
        /** The snippet root element, which can be used to set props on the component from outside (e.g. setting the room name from oldUiMessageHistory). */
        root: HTMLSvelteSnippetElement;
        /** Whether the conversation has unread messages. Shows a visual indicator if true. */
        hasUnreadMessages?: boolean;
    }

    const {
        slug,
        name,
        context,
        root,
        hasUnreadMessages = false
    }: Props = $props();

    // Binding renaming allows us to prevent the click event from propagating and opening
    // the conversation when the user is trying to rename it. (This includes hitting the space or enter key to confirm the rename, which would otherwise also trigger the conversation to open.)
    let isRenaming = $state(false);

    $effect(() => {
        return oldUiBridge.onRenameChat((renamedSlug, newName) => {
            if (slug && renamedSlug === slug) {
                root.setProps({name: newName});
            }
        });
    });

    const sharedProps: ComponentProps<typeof RoomNameMenu | typeof AiConvNameMenu> = $derived.by(() => ({
        slug,
        name,
        context,
        hasUnreadMessages,
        block: true,
        triggerIcon: EllipsisIcon,
        buttonProps: {class: 'chat-name-menu-button'}
    }));
</script>

<button
    class="sidebar-button btn-md selection-item"
    onclick={() => !isRenaming && slug && oldUiBridge.triggerOpenChat(slug)}>

    {#if context === 'room'}
        <div class="dot-lg" id="unread-msg-flag" style={'display: ' + (hasUnreadMessages ? 'block' : 'none')}></div>
    {/if}
    {#if context === 'room'}
        <RoomNameMenu
            bind:isRenaming={isRenaming}
            {...sharedProps}
        />
    {:else}
        <AiConvNameMenu
            bind:isRenaming={isRenaming}
            {...sharedProps}
        />
    {/if}
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
</style>
