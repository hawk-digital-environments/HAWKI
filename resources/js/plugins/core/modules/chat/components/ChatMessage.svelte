<script lang="ts">
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
    import Copy01Icon from '$lib/components/ui/icons/iconset/Copy01Icon.svelte';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    import MessageEdit01Icon from '$lib/components/ui/icons/iconset/MessageEdit01Icon.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import MessageCircleReplyIcon from '$lib/components/ui/icons/iconset/MessageCircleReplyIcon.svelte';
    import VolumeHighIcon from '$lib/components/ui/icons/iconset/VolumeHighIcon.svelte';
    import MessageBody from '$plugins/core/modules/chat/components/message/MessageBody.svelte';
    import type {ChatMessage as ChatMessageType} from '$plugins/core/modules/chat/types.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        message: ChatMessageType;
        composer?: ComposerContext | null;
        onDelete: (message: ChatMessageType) => void;
        onDeleteAttachment: (message: ChatMessageType, fileId: string) => void;
    }

    const {message, composer = null, onDelete, onDeleteAttachment}: Props = $props();
    const {__} = useTranslator();
    const isAssistant = $derived(message.message_role === 'assistant');

    function copyMessage() {
        navigator.clipboard.writeText(message.content.text);
    }

    function speakMessage() {
        speechSynthesis.cancel();
        speechSynthesis.speak(new SpeechSynthesisUtterance(message.content.text));
    }
</script>

<article class="message" class:user={!isAssistant} class:assistant={isAssistant}>
    <div class="avatar-wrap">
        {#if isAssistant}
            <span class="assistant-avatar"><BotIcon size={18} /></span>
        {:else}
            <Avatar src={message.author.avatar_url} name={message.author.name} size={32} />
        {/if}
    </div>
    <div class="message-column">
        <div class="meta">
            <span class="author">{isAssistant ? (message.model ?? 'HAWKI') : message.author.name}</span>
            {#if message.created_at}
                <time>{message.created_at.replace('+', ' · ')}</time>
            {/if}
        </div>

        <div class="content">
            {#if isAssistant}
                <MessageBody message={message.content.text} citations={message.citations} isStreaming={message.isStreaming} />
            {:else}
                <p>{message.content.text}</p>
            {/if}

            {#if message.content.attachments?.length}
                <div class="attachments">
                    {#each message.content.attachments as attachment (attachment.fileData.uuid)}
                        <a href={attachment.fileData.url} target="_blank" rel="noreferrer" download={attachment.fileData.name}>
                            {attachment.fileData.name}
                        </a>
                        <button class="remove-attachment" title={__('chat.actions.deleteAttachment')} aria-label={__('chat.actions.deleteAttachment')} onclick={() => onDeleteAttachment(message, attachment.fileData.uuid)}>×</button>
                    {/each}
                </div>
            {/if}

            {#if message.isStreaming && !message.content.text}
                <span class="stream-status">{message.status ?? __('chat.page.thinking')}</span>
            {/if}
        </div>

        {#if !message.isStreaming}
            <div class="actions">
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={Copy01Icon} tooltip={__('chat.actions.copy')} onclick={copyMessage} />
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={VolumeHighIcon} tooltip={__('chat.actions.speak')} onclick={speakMessage} />
                {#if composer && !isAssistant}
                    <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={MessageEdit01Icon} tooltip={__('chat.actions.edit')} onclick={() => composer?.mode.enter('edit', message)} />
                {/if}
                {#if composer && isAssistant}
                    <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={ArrowReloadHorizontalIcon} tooltip={__('chat.actions.regenerate')} onclick={() => composer?.mode.enter('regen', message)} />
                {/if}
                {#if composer}
                    <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={MessageCircleReplyIcon} tooltip={__('chat.actions.thread')} onclick={() => composer?.mode.enter('thread', message.message_id.split('.')[0])} />
                {/if}
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={Delete02Icon} tooltip={__('chat.actions.delete')} onclick={() => onDelete(message)} />
            </div>
        {/if}
    </div>
</article>

<style>
    .message {
        display: grid;
        grid-template-columns: 2rem minmax(0, 1fr);
        gap: var(--space-3);
        width: 100%;
    }

    .avatar-wrap { padding-top: var(--space-0_5); }

    .assistant-avatar {
        display: grid;
        width: 2rem;
        height: 2rem;
        place-items: center;
        border-radius: var(--corner-full);
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    .message-column { min-width: 0; }

    .meta {
        display: flex;
        align-items: baseline;
        gap: var(--space-2);
        margin-bottom: var(--space-1);
        font-size: var(--font-size-xs);
    }

    .author { font-weight: var(--font-weight-semibold); }
    time { color: var(--color-text-muted); font-size: var(--font-size-xxs); }

    .content {
        color: var(--color-text);
        line-height: var(--line-height-relaxed);
    }

    .user .content {
        display: inline-block;
        max-width: min(100%, 42rem);
        padding: var(--space-2_5) var(--space-3);
        border-radius: var(--corner-lg);
        background: var(--color-surface-light);
    }

    p { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }

    .attachments {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        margin-top: var(--space-2);
    }

    .attachments a {
        padding: var(--space-1) var(--space-2);
        border: var(--border);
        border-radius: var(--corner-sm);
        color: var(--color-text);
        font-size: var(--font-size-xs);
        text-decoration: none;
    }

    .remove-attachment {
        width: 1.75rem;
        height: 1.75rem;
        border: var(--border);
        border-radius: var(--corner-full);
        background: transparent;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .remove-attachment:hover { color: var(--color-error); }

    .stream-status { color: var(--color-text-muted); font-size: var(--font-size-xs); }

    .actions {
        display: flex;
        gap: var(--space-0_5);
        min-height: 2rem;
        margin-top: var(--space-1);
        opacity: 0;
        transition: opacity var(--duration-fast);
    }

    .message:hover .actions,
    .actions:focus-within { opacity: 1; }

    @media (hover: none) { .actions { opacity: 1; } }
</style>
