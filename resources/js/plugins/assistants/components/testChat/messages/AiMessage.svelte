<script lang="ts">
    import type {ChatMessage} from "../types";
    import TextPart from "./parts/TextPart.svelte";
    import ReasoningPart from "./parts/ReasoningPart.svelte";
    import ToolCallPart from "./parts/ToolCallPart.svelte";
    import AppliedPart from "./parts/AppliedPart.svelte";
    import StatusSteps, {type StatusStep} from "$lib/components/ui/status-steps/StatusSteps.svelte";
    import MessageSearch01Icon from "$lib/components/ui/icons/iconset/MessageSearch01Icon.svelte";
    import FileSearchIcon from "$lib/components/ui/icons/iconset/FileSearchIcon.svelte";
    import AiBrain01Icon from "$lib/components/ui/icons/iconset/AiBrain01Icon.svelte";
    import AiEditingIcon from "$lib/components/ui/icons/iconset/AiEditingIcon.svelte";
    import MailSend01Icon from "$lib/components/ui/icons/iconset/MailSend01Icon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import {useChatConfig} from "../stream/chatConfig.svelte.js";

    let {message} = $props<{ message: ChatMessage }>();

    const {__} = useTranslator();
    const config = useChatConfig();

    // Neither endpoint reports progress before the first output, so the
    // stages are timed. Each is true for every request: the guide always reads
    // the message, then the draft and its knowledge files, then generates.
    const pendingSteps: StatusStep[] = config.variant === "guide"
        ? [
            {icon: MessageSearch01Icon, label: __("assistants.testChat.status.reading"), at: 0},
            {icon: FileSearchIcon, label: __("assistants.testChat.status.reviewing"), at: 1200},
            {icon: AiBrain01Icon, label: __("assistants.testChat.status.thinking"), at: 2800},
            {icon: AiEditingIcon, label: __("assistants.testChat.status.writing"), at: 6000},
        ]
        : [
            {icon: MailSend01Icon, label: __("assistants.testChat.status.sending"), at: 0},
            {icon: AiBrain01Icon, label: __("assistants.testChat.status.thinking"), at: 900},
        ];
</script>

<!-- Unboxed, like the main chat's assistant messages: the reply is plain
     text on the panel surface; only the user's turns sit in bubbles. -->
<div class="ai-message" aria-busy={message.streaming}>
    {#each message.parts as part, i (i)}
        {#if part.type === "text"}
            <TextPart text={part.text} streaming={message.streaming}/>
        {:else if part.type === "reasoning"}
            <ReasoningPart text={part.text} active={message.streaming && i === message.parts.length - 1}/>
        {:else if part.type === "tool-call"}
            <ToolCallPart name={part.name}/>
        {:else if part.type === "applied"}
            <AppliedPart fields={part.fields}/>
        {/if}
    {/each}
    {#if message.streaming && message.parts.length === 0}
        <p class="pending"><StatusSteps steps={pendingSteps}/></p>
    {/if}
</div>

<style>
    .ai-message {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        min-width: 0;
    }

    .pending {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
    }
</style>
