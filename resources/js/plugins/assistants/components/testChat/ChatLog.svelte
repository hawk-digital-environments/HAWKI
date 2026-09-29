<script lang="ts">
    import UserMessage from "./messages/UserMessage.svelte";
    import AiMessage from "./messages/AiMessage.svelte";
    import {useChatStore} from "./stream/chatStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import {useChatConfig} from "./stream/chatConfig.svelte.js";
    import AssistantAvatarIcon from "$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte";
    import {resolveAssistantAvatar} from "$plugins/assistants/utils/resolveAssistantAvatar";
    import {StatusIcon} from "$lib/components/ui/icons";
    import AiMagicIcon from "$lib/components/ui/icons/iconset/AiMagicIcon.svelte";

    const chat = useChatStore();
    const {__} = useTranslator();
    const config = useChatConfig();
    const messages = $derived(chat.messages);
    const assistant = $derived(config.assistant);
    const avatar = $derived(resolveAssistantAvatar(assistant.avatar, assistant.name));

    let scroller = $state<HTMLDivElement | null>(null);

    $effect(() => {
        for (const m of messages) {
            void m.parts.length;
            for (const p of m.parts) {
                if (p.type === "text") void p.text;
            }
        }
        if (scroller) {
            scroller.scrollTop = scroller.scrollHeight;
        }
    });
</script>

<div class="chatlog" bind:this={scroller} class:empty={messages.length === 0}>
    {#if messages.length === 0}
        <div class="chatlog-empty">
            {#if config.variant === "guide"}
                <StatusIcon icon={AiMagicIcon} tone="neutral" size="xl"/>
                <p class="empty-title">{__('assistants.builder.guide.empty_title')}</p>
                <p class="empty-hint">{__('assistants.builder.guide.empty_hint')}</p>
            {:else}
                {#if config.hasModel}
                    <AssistantAvatarIcon size="small" assistantAvatar={avatar}/>
                {:else}
                    <!-- No model yet: an unfilled outline of the avatar. -->
                    <div class="avatar-placeholder" aria-hidden="true">{avatar.name}</div>
                {/if}
                <p class="empty-title">{assistant.name.trim() || __('assistants.testChat.unnamed')}</p>
                <p class="empty-hint">
                    {config.hasModel ? __('assistants.testChat.empty_hint') : __('assistants.testChat.no_model_hint')}
                </p>
            {/if}
        </div>
    {/if}
    {#each messages as message, i (i)}
        {#if message.role === "user"}
            <UserMessage {message}/>
        {:else}
            <AiMessage {message}/>
        {/if}
    {/each}
    {#if chat.status === "error" && chat.error}
        <p class="chat-error" role="alert">{chat.error}</p>
    {/if}
</div>

<style>
    .chatlog {
        flex-grow: 1;
        min-height: 12rem;
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        padding: var(--space-4);
        overflow-y: auto;
    }

    /* Centre the hint while the log is empty. */
    .chatlog.empty {
        align-items: center;
        justify-content: center;
    }

    .chatlog-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        max-width: 18rem;
        text-align: center;
    }

    /* Between the avatar's small and medium presets. */
    .chatlog-empty :global(.icon-container) {
        width: 4rem;
        height: 4rem;
    }

    .chatlog-empty :global(.icon-container .symbol) {
        font-size: 2rem;
    }

    .avatar-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 4rem;
        height: 4rem;
        box-sizing: border-box;
        border: 1.5px dashed var(--color-border);
        border-radius: var(--corner-md);
        font-size: 2rem;
        line-height: 1;
    }

    .empty-title {
        margin: var(--space-3) 0 0;
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    .chat-error {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-error);
    }

    .empty-hint {
        margin: var(--space-1) 0 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
</style>
