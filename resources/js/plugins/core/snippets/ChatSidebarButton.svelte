<!--
@component Renders one entry in the chat sidebar list (either an AI conversation or a group room),
as a `<svelte-snippet>` entry point registered by `core.plugin.ts`. The row is a button that opens
the conversation via `oldUiBridge.triggerOpenChat`; next to it — as a sibling, never nested — sits
the actions menu (`RoomNameMenu`/`AiConvNameMenu` depending on `context`) whose "Rename" swaps the
row for an inline text field. Rooms additionally show an unread-message indicator (with a
screen-reader text equivalent).

The menu trigger is hidden until the row is hovered, focused (keyboard) or its menu is open.

This is probably a temporary component, since it mixes concerns between chat and room messages.
However, as we want to merge them both anyway, it's not worth the effort to split out the
chat-specific parts into a separate component for now.

Usage (created dynamically by the legacy sidebar JS — see `public/js/ai_chat_functions.js`):
```js
const snippet = document.createElement('svelte-snippet');
snippet.setAttribute('type', 'ChatSidebarButton');
snippet.setProps({slug: conv.slug, name: conv.conv_name, context: 'aiConv'});
snippet.setAttribute('data-room-slug', conv.slug); // used to look the button up again later
document.getElementById('chats-list').appendChild(snippet);
```

Later, the name can be updated from outside without remounting:
```js
document.querySelector(`svelte-snippet[type="ChatSidebarButton"][data-room-slug="${slug}"]`)
    .setProps({name: newName});
```
-->
<script lang="ts">
    import type {ComposerContextType} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
    import type {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';
    import type {ComponentProps} from 'svelte';
    import EllipsisIcon from '$lib/components/ui/icons/iconset/EllipsisIcon.svelte';
    import RoomNameMenu from '$plugins/core/modules/chat/components/nameMenu/RoomNameMenu.svelte';
    import AiConvNameMenu from '$plugins/core/modules/chat/components/nameMenu/AiConvNameMenu.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

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

    const {__} = useTranslator();

    // While renaming, the name menu swaps in its text field and the row button
    // is removed, so the field is never nested inside another control.
    let isRenaming = $state(false);
    // Keeps the (otherwise hover-only) trigger visible while its menu is showing.
    let menuOpen = $state(false);
    let rowButton = $state<HTMLButtonElement | null>(null);

    const showUnread = $derived(context === 'room' && hasUnreadMessages);

    $effect(() => {
        return oldUiBridge.onRenameChat((renamedSlug, newName) => {
            if (slug && renamedSlug === slug) {
                root.setProps({name: newName});
            }
        });
    });

    const sharedProps: ComponentProps<typeof RoomNameMenu | typeof AiConvNameMenu> = $derived.by(() => ({
        slug: slug ?? '',
        name: name ?? '',
        hasUnreadMessages,
        showName: false,
        triggerIcon: EllipsisIcon,
        buttonProps: {class: 'chat-name-menu-button'},
        // The row button is re-rendered once renaming ends; the menu focuses it after that.
        focusAfterRename: () => rowButton
    }));
</script>

<div class="chat-row" class:menu-open={menuOpen} class:renaming={isRenaming}>
    {#if !isRenaming}
        <button
            type="button"
            class="sidebar-button"
            bind:this={rowButton}
            onclick={() => slug && oldUiBridge.triggerOpenChat(slug)}>
            {#if showUnread}
                <span class="dot-lg unread-dot" aria-hidden="true"></span>
            {/if}
            <span class="name">{name}</span>
            {#if showUnread}
                <span class="u-sr-only">, {__('chat.sidebar.unreadMessages')}</span>
            {/if}
        </button>
    {/if}
    {#if context === 'room'}
        <RoomNameMenu
            bind:isRenaming={isRenaming}
            bind:open={menuOpen}
            {...sharedProps}
        />
    {:else}
        <AiConvNameMenu
            bind:isRenaming={isRenaming}
            bind:open={menuOpen}
            {...sharedProps}
        />
    {/if}
</div>

<style>
    :global(.chat-name-menu-button) {
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    /* Touch has no hover, so the trigger stays visible there. */
    @media (hover: none) {
        :global(.chat-name-menu-button) {
            opacity: 1;
        }
    }

    .chat-row {
        display: flex;
        flex-direction: row;
        align-items: center;
        column-gap: .25rem;
        min-height: 2.5rem;
        padding-left: 1rem;
        padding-right: .5rem;
        border-radius: 5px;
        background-color: transparent;
        transition: background-color var(--transition-fast);

        /* The trigger shows for the pointer, for keyboard focus anywhere in
           the row, and while its menu is open. */
        &:hover,
        &:focus-within,
        &.menu-open {
            :global(.chat-name-menu-button) {
                opacity: 1;
            }
        }

        &:not(.renaming):hover,
        &:not(.renaming):focus-within {
            background-color: var(--panel-main);
        }
    }

    :global(.active) .chat-row {
        background-color: var(--highlight-color);
    }

    .sidebar-button {
        display: flex;
        flex: 1;
        min-width: 0;
        flex-direction: row;
        column-gap: .5rem;
        align-items: center;
        height: 2.5rem;
        padding: 0;
        border: none;
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
        outline-offset: -2px;
    }

    .unread-dot {
        flex-shrink: 0;
    }

    .name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
