<!--
  @component One message in the chat log: avatar, author/timestamp line, the
  rendered body (Markdown, citations, attachments) and the per-message action
  bar (copy, edit, regenerate-with-model menu, delete). Marks itself busy while pending.

  A trunk message additionally renders its thread: a toggle row with the reply
  count and, when open, the replies (this component, nested with
  `isThreadReply`) indented behind a rail. The thread opens itself while the
  composer writes into it or a reply is still streaming in, and closes again
  when thread mode is left without any replies.
-->
<script lang="ts">
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
    import Copy01Icon from '$lib/components/ui/icons/iconset/Copy01Icon.svelte';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    import MessageEdit01Icon from '$lib/components/ui/icons/iconset/MessageEdit01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import MessageCircleReplyIcon from '$lib/components/ui/icons/iconset/MessageCircleReplyIcon.svelte';
    import VolumeHighIcon from '$lib/components/ui/icons/iconset/VolumeHighIcon.svelte';
    import ChatMessageSelf from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import MessageBody from '$plugins/core/modules/chat/components/message/MessageBody.svelte';
    import MessageReasoning from '$plugins/core/modules/chat/components/message/MessageReasoning.svelte';
    import RegenerateMenu from '$plugins/core/modules/chat/components/message/RegenerateMenu.svelte';
    import MessageStats from '$plugins/core/modules/chat/components/message/MessageStats.svelte';
    import type {ChatMessage as ChatMessageType} from '$plugins/core/modules/chat/types.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {messageTrunkId} from '$plugins/core/modules/chat/utils/messageThreads.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {formatDateTime} from '$lib/utils/date.js';

    interface Props extends HTMLAttributes<HTMLElement> {
        message: ChatMessageType;
        /** Thread replies of this trunk message, in creation order. */
        replies?: ChatMessageType[];
        /** True when this message is itself a thread reply — it gets no thread of its own. */
        isThreadReply?: boolean;
        composer?: ComposerContext | null;
        /** Enables the regenerate menu on assistant messages; `model` is `null` for "same model as before". */
        onRegenerate?: (message: ChatMessageType, model: AiModel | null) => void;
        onDelete: (message: ChatMessageType) => void;
        onDeleteAttachment: (message: ChatMessageType, fileId: string) => void;
    }

    const {message, replies = [], isThreadReply = false, composer = null, onRegenerate, onDelete, onDeleteAttachment, class: className, ...restProps}: Props = $props();
    const {__} = useTranslator();
    const aiModelStore = useStore('ai-models');
    const experiments = useStore('experiments');
    const isAssistant = $derived(message.message_role === 'assistant');
    const showStats = $derived(isAssistant && experiments.isEnabled('statsForNerds') && Boolean(message.stats));
    const isReasoning = $derived(message.status === 'reasoning' || message.status === 'reasoning_delta');
    const streamStatusLabel = $derived(
        isReasoning || !message.status || message.status === 'running' ? __('chat.page.thinking') : __('chat.page.generating')
    );
    const authorName = $derived(
        isAssistant
            ? aiModelStore.getOneById(message.model ?? '')?.label ?? message.model ?? 'HAWKI'
            : message.author.name
    );

    const trunkId = $derived(messageTrunkId(message));
    /** True while the composer's thread mode targets this message's thread. */
    const composingInThread = $derived.by(() => {
        if (isThreadReply || trunkId === null || !composer?.mode.isThread) return false;
        return Number(composer.mode.getState('thread').threadId) === trunkId;
    });
    /** Threads hang off persisted trunk messages only. */
    const canThread = $derived(Boolean(composer) && !isThreadReply && trunkId !== null && !message.isPending && !message.isStreaming);
    const hasThreadSection = $derived(!isThreadReply && (replies.length > 0 || composingInThread));
    const threadToggleLabel = $derived(
        replies.length === 0
            ? __('chat.thread.title')
            : replies.length === 1
                ? __('chat.thread.replyCountOne')
                : __('chat.thread.replyCount', {count: String(replies.length)})
    );

    let threadOpen = $state(false);
    let wasComposingInThread = false;

    // The thread follows the composer: entering thread mode opens it, leaving
    // the mode with nothing written closes it again (mirrors the legacy UI).
    $effect(() => {
        if (composingInThread) {
            threadOpen = true;
        } else if (wasComposingInThread && replies.length === 0) {
            threadOpen = false;
        }
        wasComposingInThread = composingInThread;
    });

    // A reply arriving right now (sent or streaming) must be visible.
    $effect(() => {
        if (replies.some(reply => reply.isPending || reply.isStreaming)) threadOpen = true;
    });

    function openThreadComposer() {
        if (trunkId === null) return;
        threadOpen = true;
        composer?.mode.enter('thread', String(trunkId));
    }

    function copyMessage() {
        navigator.clipboard.writeText(message.content.text);
    }

    function speakMessage() {
        speechSynthesis.cancel();
        speechSynthesis.speak(new SpeechSynthesisUtterance(message.content.text));
    }

    const threadPanelId = $props.id();
</script>

<article {...restProps} class={["message", className]} class:user={!isAssistant} class:assistant={isAssistant} class:pending={message.isPending} aria-busy={message.isPending}>
    <div class="avatar-wrap">
        {#if isAssistant}
            <span class="assistant-avatar" aria-hidden="true"><BotIcon size={18} /></span>
        {:else}
            <Avatar src={message.author.avatar_url} name={message.author.name} label={message.author.name} size={32} />
        {/if}
    </div>
    <div class="message-column">
        <div class="meta">
            <span class="author">{authorName}</span>
            {#if message.created_at}
                <time datetime={message.created_at}>{formatDateTime(message.created_at)}</time>
            {/if}
        </div>

        <div class="content">
            {#if isAssistant && message.reasoning?.length}
                <MessageReasoning parts={message.reasoning} active={Boolean(message.isStreaming && !message.content.text)} />
            {/if}
            {#if isAssistant}
                <MessageBody message={message.content.text} citations={message.citations} isStreaming={message.isStreaming} />
            {:else}
                <p>{message.content.text}</p>
            {/if}

            {#if message.content.attachments?.length}
                <div class="attachments">
                    {#each message.content.attachments as attachment (attachment.fileData.uuid)}
                        {#if message.isPending}
                            <span class="pending-attachment">{attachment.fileData.name}</span>
                        {:else}
                            <a href={attachment.fileData.url} target="_blank" rel="noreferrer" download={attachment.fileData.name}>
                                {attachment.fileData.name}
                            </a>
                            <button class="remove-attachment" title={__('chat.actions.deleteAttachment')} aria-label={__('chat.actions.deleteAttachment')} onclick={() => onDeleteAttachment(message, attachment.fileData.uuid)}>×</button>
                        {/if}
                    {/each}
                </div>
            {/if}

            {#if message.isStreaming && !message.content.text && !message.reasoning?.length}
                <span class="stream-status">{streamStatusLabel}</span>
            {/if}

            {#if showStats && message.stats}
                <MessageStats stats={message.stats} />
            {/if}
        </div>

        {#if !message.isStreaming && !message.isPending}
            <div class="actions">
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={Copy01Icon} tooltip={__('chat.actions.copy')} onclick={copyMessage} />
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={VolumeHighIcon} tooltip={__('chat.actions.speak')} onclick={speakMessage} />
                {#if composer && !isAssistant}
                    <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={MessageEdit01Icon} tooltip={__('chat.actions.edit')} onclick={() => composer?.mode.enter('edit', message)} />
                {/if}
                {#if onRegenerate && isAssistant}
                    <RegenerateMenu {message} {onRegenerate} />
                {/if}
                {#if canThread}
                    <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={MessageCircleReplyIcon} tooltip={__('chat.actions.thread')} onclick={openThreadComposer} />
                {/if}
                <ButtonWithTooltip variant="iconGhost" size="xs" iconLeft={Delete02Icon} tooltip={__('chat.actions.delete')} onclick={() => onDelete(message)} />
            </div>
        {/if}

        {#if hasThreadSection}
            <div class="thread" class:composing={composingInThread}>
                <button
                    type="button"
                    class="thread-toggle"
                    class:open={threadOpen}
                    aria-expanded={threadOpen}
                    aria-controls={threadPanelId}
                    onclick={() => threadOpen = !threadOpen}
                >
                    <MessageCircleReplyIcon size={16} />
                    <span class="thread-label">{threadToggleLabel}</span>
                    <span class="thread-chevron" aria-hidden="true"><ArrowRight01Icon size={16} /></span>
                </button>

                {#if threadOpen}
                    <div class="thread-panel" id={threadPanelId} transition:growTransition>
                        {#each replies as reply (reply.clientKey ?? reply.message_id)}
                            <ChatMessageSelf message={reply} {composer} {onRegenerate} isThreadReply {onDelete} {onDeleteAttachment} />
                        {/each}

                        {#if composingInThread}
                            <div class="thread-active" role="status">
                                <span class="thread-active-dot" aria-hidden="true"></span>
                                {__('chat.thread.writing')}
                            </div>
                        {:else if canThread}
                            <div class="thread-reply">
                                <Button variant="ghost" size="xs" iconLeft={MessageCircleReplyIcon} onclick={openThreadComposer}>
                                    {__('chat.thread.reply')}
                                </Button>
                            </div>
                        {/if}
                    </div>
                {/if}
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

    .pending { opacity: 0.72; }

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

    .attachments a,
    .pending-attachment {
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

    /* ── Thread ───────────────────────────────────────────────────────── */

    .thread { margin-top: var(--space-1); }

    /* Same quiet pill as the reasoning trigger: a line of accent text with a
       chevron, pulled left by its own padding so the icon aligns with the
       message content. */
    .thread-toggle {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        max-width: 100%;
        margin-inline-start: calc(-1 * var(--space-3));
        padding: var(--space-1_5) var(--space-3);
        border: none;
        border-radius: var(--corner-full);
        background: transparent;
        color: var(--color-accent-text);
        font: inherit;
        font-size: var(--font-size-xs);
        cursor: pointer;
        user-select: none;
        transition: background-color var(--duration-fast);
    }

    .thread-toggle:hover { background: var(--color-hover); }
    .thread-toggle:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; }

    .thread-label { font-weight: var(--font-weight-medium); }

    .thread-chevron {
        display: inline-flex;
        flex: none;
        transition: transform var(--duration-fast) var(--easing-spring);
    }

    .thread-toggle.open .thread-chevron { transform: rotate(90deg); }

    /* The replies hang behind a rail, indented like the legacy branch view.
       While the composer writes into this thread the rail takes the accent
       colour, echoing the pulsing indicator at its foot. */
    .thread-panel {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        margin-top: var(--space-2);
        padding: var(--space-2) 0 var(--space-2) var(--space-4);
        border-left: 2px solid var(--color-border);
    }

    .composing .thread-panel { border-left-color: var(--color-accent-text); }

    .thread-reply { align-self: flex-start; }

    .thread-active {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .thread-active-dot {
        width: 10px;
        height: 10px;
        border-radius: var(--corner-full);
        background: var(--color-accent-text);
        animation: thread-active-pulse 1.6s ease-out infinite;
    }

    @keyframes thread-active-pulse {
        0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-accent-text) 60%, transparent); }
        70% { box-shadow: 0 0 0 6px transparent; }
        100% { box-shadow: 0 0 0 0 transparent; }
    }

    @media (prefers-reduced-motion: reduce) {
        .thread-active-dot { animation: none; }
        .thread-chevron { transition: none; }
    }
</style>
