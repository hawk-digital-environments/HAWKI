<!--
  @component Status and controls of the "Chat Index" experiment (see
  `ChatIndexStore`). Two faces: the full panel on the experiments settings
  page — counts, last build, rebuild, JSON export and clear — and, with `compact`, the
  one-line prompt the search palette shows when the user has several chats
  but no index yet ("build it now?"). Both show rebuild progress while one
  is running.
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import Refresh01Icon from '$lib/components/ui/icons/iconset/Refresh01Icon.svelte';
    import DatabaseSearchIcon from '$lib/components/ui/icons/iconset/DatabaseSearchIcon.svelte';
    import Download01Icon from '$lib/components/ui/icons/iconset/Download01Icon.svelte';
    import {downloadText} from '$lib/utils/download.js';
    import {formatDateTime} from '$lib/utils/date.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Single-line prompt with a build button, for the search palette. */
        compact?: boolean;
    }

    const {compact = false}: Props = $props();

    const chatIndex = useStore('chat-index');
    const chatStore = useStore('chat');
    const {__} = useTranslator();

    /** Downloads the decrypted index as readable JSON, for inspection. */
    function exportIndex(): void {
        downloadText('chat-index.json', JSON.stringify(chatIndex.toDocument(), null, 2), 'application/json');
    }

    // Same formatting as message timestamps (`ChatMessage`), browser locale.
    const builtAt = $derived(chatIndex.builtAt ? formatDateTime(chatIndex.builtAt) : null);

    const status = $derived.by(() => {
        if (chatIndex.building) {
            return __('ui.settings.experiments.chatIndex.building', {
                done: String(chatIndex.progress.done),
                total: String(chatIndex.progress.total)
            });
        }
        if (!chatIndex.storageAvailable) return __('ui.settings.experiments.chatIndex.unavailable');
        if (chatIndex.conversationCount === 0) return __('ui.settings.experiments.chatIndex.empty');
        return __('ui.settings.experiments.chatIndex.status', {
            conversations: String(chatIndex.conversationCount),
            messages: String(chatIndex.messageCount)
        });
    });
</script>

{#if compact}
    <div class="chat-index-prompt" role="status">
        <DatabaseSearchIcon size={16} strokeWidth={2} />
        <span class="prompt-text">
            {#if chatIndex.building}
                {status}
            {:else}
                {__('ui.settings.experiments.chatIndex.promptText', {count: String(chatStore.conversations.length)})}
            {/if}
        </span>
        {#if !chatIndex.building}
            <Button size="xs" variant="accent" onclick={() => void chatIndex.rebuild()}>
                {__('ui.settings.experiments.chatIndex.build')}
            </Button>
            <Button size="xs" variant="ghost" onclick={() => (chatIndex.dismissed = true)}>
                {__('ui.settings.experiments.chatIndex.later')}
            </Button>
        {/if}
    </div>
{:else}
    <div class="chat-index-panel">
        <h3>{__('ui.settings.experiments.chatIndex.title')}</h3>
        <p class="status" aria-live="polite">{status}</p>
        {#if builtAt && !chatIndex.building}
            <p class="hint">{__('ui.settings.experiments.chatIndex.builtAt', {date: builtAt})}</p>
        {/if}
        {#if chatIndex.persistent === false}
            <p class="hint">{__('ui.settings.experiments.chatIndex.notPersistent')}</p>
        {/if}
        {#if chatIndex.error}
            <p class="error">{__('ui.settings.experiments.chatIndex.error', {message: chatIndex.error})}</p>
        {/if}
        <div class="actions">
            <Button
                size="sm"
                variant="stroke"
                iconLeft={Refresh01Icon}
                disabled={chatIndex.building || !chatIndex.storageAvailable}
                onclick={() => void chatIndex.rebuild()}
            >
                {chatIndex.hasIndex
                    ? __('ui.settings.experiments.chatIndex.refresh')
                    : __('ui.settings.experiments.chatIndex.build')}
            </Button>
            {#if chatIndex.conversationCount > 0}
                <Button
                    size="sm"
                    variant="ghost"
                    iconLeft={Download01Icon}
                    disabled={chatIndex.building}
                    onclick={exportIndex}
                >
                    {__('ui.settings.experiments.chatIndex.export')}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={chatIndex.building}
                    onclick={() => void chatIndex.clear()}
                >
                    {__('ui.settings.experiments.chatIndex.clear')}
                </Button>
            {/if}
        </div>
    </div>
{/if}

<style>
    .chat-index-panel {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        padding: var(--space-3);
        border: var(--border);
        border-radius: var(--corner-md);
    }

    h3,
    p {
        margin: 0;
        font-size: var(--font-size-xs);
    }

    h3 {
        font-weight: var(--font-weight-medium);
    }

    .status,
    .hint {
        color: var(--color-text-muted);
    }

    .hint {
        font-size: var(--font-size-xxs);
    }

    .error {
        color: var(--color-error);
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        margin-top: var(--space-1);
    }

    .chat-index-prompt {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        border-bottom: var(--border);
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
    }

    .prompt-text {
        flex: 1;
        min-width: 0;
    }
</style>
