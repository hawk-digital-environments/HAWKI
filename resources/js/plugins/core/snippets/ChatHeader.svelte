<!--
@component Sticky header above the active chat, showing the conversation/room name (with an
inline rename menu) and the export menu — a `<svelte-snippet>` entry point registered by
`core.plugin.ts`. Only renders once a conversation is actually open (`oldUiMessageHistory.isInConversation`);
name/slug are read reactively from `oldUiMessageHistory`, since that legacy store still owns
"which conversation is currently open" state.

Rendered once per page for either an AI conversation or a group room chat (see
`resources/views/modules/chat.blade.php` and `resources/views/modules/groupchat.blade.php`):
```blade
<x-svelte type="ChatHeader" :props="['context' => 'aiConv']" />
<x-svelte type="ChatHeader" :props="['context' => 'room']" />
```
-->
<script lang="ts">

    import type {ComposerContextType} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import type {ComponentProps} from 'svelte';
    import AiConvNameMenu from '$plugins/core/modules/chat/components/nameMenu/AiConvNameMenu.svelte';
    import RoomNameMenu from '$plugins/core/modules/chat/components/nameMenu/RoomNameMenu.svelte';
    import ExportMenu from '$plugins/core/modules/chat/components/header/ExportMenu.svelte';
    import {exportConversation} from '$plugins/core/modules/chat/utils/exportConversation.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Which kind of chat this header belongs to ('aiConv' for a 1:1 AI conversation, 'room' for a group room); selects between `AiConvNameMenu` and `RoomNameMenu`. */
        context: ComposerContextType;
    }

    const {context: contextType = 'aiConv'}: Props = $props();
    const {__} = useTranslator();

    const name = $derived(oldUiMessageHistory.conversationName);
    const slug = $derived(oldUiMessageHistory.conversationSlug);
    const exportLabels = $derived({
        systemPrompt: __('chat.export.systemPrompt'),
        conversation: __('chat.export.conversation'),
        attachments: __('chat.export.attachments')
    });

    const sharedProps: ComponentProps<typeof RoomNameMenu | typeof AiConvNameMenu> = $derived.by(() => ({
        slug,
        name,
        context: contextType
    }));
</script>
{#if oldUiMessageHistory.isInConversation}
    <div class="chat-header" transition:growTransition>
        <div class="left-section">
            {#if contextType === 'room'}
                <RoomNameMenu {...sharedProps}/>
            {:else}
                <AiConvNameMenu {...sharedProps}/>
            {/if}
        </div>
        <div class="right-section">
            <ExportMenu onExport={format => {
                if (oldUiMessageHistory.conversation) {
                    return exportConversation(oldUiMessageHistory.conversation, format, exportLabels);
                }
            }}/>
        </div>
    </div>
{/if}
<style>
    .chat-header {
        background: var(--color-bg-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-2, 0.5rem) var(--space-4, 1rem);
        box-shadow: 0 5px 20px 20px var(--color-bg-secondary);
        z-index: 1000;
        width: 100%;

        & .left-section {
            flex-shrink: 1;
            display: flex;
            width: 70%;
        }

        & .right-section {
            display: flex;
            align-items: flex-end;
            gap: var(--space-2, 0.5rem);
        }
    }

    :global(svelte-snippet[type="ChatHeader"]) {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        width: auto;
    }
</style>
