<script lang="ts">

    import ExportMenu from '$lib/components/chat/header/ExportMenu.svelte';
    import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import type {ComponentProps} from 'svelte';
    import RoomNameMenu from '$lib/components/chat/nameMenu/RoomNameMenu.svelte';
    import AiConvNameMenu from '$lib/components/chat/nameMenu/AiConvNameMenu.svelte';

    interface Props {
        context: ComposerContextType;
    }

    const {context: contextType = 'aiConv'}: Props = $props();

    const name = $derived(oldUiMessageHistory.conversationName);
    const slug = $derived(oldUiMessageHistory.conversationSlug);

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
            <ExportMenu/>
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
