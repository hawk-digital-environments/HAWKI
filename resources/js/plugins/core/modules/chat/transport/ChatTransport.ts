import type { HawkiApp } from '$lib/kernel/HawkiApp.js';
import type { ChatAssistantIdentity, ChatMessage, MessageStats } from '$plugins/core/modules/chat/types.js';
import type {
    MessageSenderTransportInterface,
    MessageSenderTransportOptions
} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import type { ChatStore } from '$plugins/core/stores/ChatStore.svelte.js';
import { aiPacketText } from '$lib/kernel/ai/AiApi.js';
import type { AiMessage, AiStreamRequest } from '$lib/kernel/ai/types.js';
import { SendMessageResponse } from '$plugins/core/modules/chat/components/composer/contexts/sending/SendMessageResponse.svelte.js';
import { applyThinkingEvent, emptyThinkingTimeline } from '$plugins/core/modules/chat/utils/thinkingEvents.js';
import type { UrlCitation } from '$lib/components/ui/citations/types.js';
import type { ComposerContext } from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import { validatedToolSnapshot } from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import type { ChatSendDescriptor } from '$plugins/core/modules/chat/hooks/chatSendHooks.js';
import type { ChatRegeneratePick } from '$plugins/core/modules/chat/hooks/chatRegenerateHooks.js';

/** How many trailing messages of a conversation or thread are sent to the model per run
 *  (see `ChatTransport.messageHistory`); older ones are dropped from the request. */
export const MESSAGE_HISTORY_CAP = 20;

interface ChatTransportOptions {
    onConversationCreated?: (slug: string) => void;
    /** Shows the first message while the server is still creating its conversation. */
    onConversationPending?: (message: ChatMessage | null) => void;
}

/** Everything one assistant reply needs, independent of whether the composer or the
 *  regenerate action on a message asked for it. */
interface AssistantRequest {
    modelId: string;
    /** Tools in their transfer-string form (see `AiToolOrCapabilityWithState.toTransferString`). */
    tools: string[];
    params: AiStreamRequest['params'];
    systemPrompt: string;
    /** Thread the reply belongs to; `0` for the trunk conversation. */
    threadId: number;
    /** Id of the assistant message to replace in place; `null` for a fresh reply. */
    regenerateMessageId: string | null;
    /**
     * Assistant handle the run is bound to, resolved by the `chatSend` hook; null for a
     * plain run (see {@link ChatTransport.regenerateMessage}).
     */
    assistantHandle?: string | null;
    /** Display author for the streamed assistant message; falls back to the default HAWKI author. */
    author?: ChatMessage['author'] | null;
    /** Display identity persisted on the AI message, so the log keeps showing who answered after reload. */
    assistant?: ChatAssistantIdentity;
}

export class ChatTransport implements MessageSenderTransportInterface {
    constructor(
        private readonly app: HawkiApp,
        private readonly store: ChatStore,
        private readonly options: ChatTransportOptions = {}
    ) {}

    public async improveMessage(message: string, _chatSystemPrompt: string): Promise<string> {
        const models = this.app.stores.get('ai-models');
        const prompts = this.app.stores.get('system-prompts');
        const model =
            models.getSystemModelByType('prompt_improvement') ??
            models.getSystemModelByType('default') ??
            models.models[0];
        if (!model) throw new Error(this.app.translator.__('chat.page.noImprovementModel'));

        const systemPrompt =
            `${prompts.getPromptByType('prompt_improvement').prompt}\n\n` +
            'You are currently in a one-on-one chat. Improve the user message for an AI assistant.\n' +
            `You MUST answer in the language with code: ${this.app.localization.locale.lang}.\n` +
            'Return only the improved message. If it cannot be improved, start the response with [NOT_IMPROVED].';
        const result = await this.app.aiApi.text({
            model: model.model_id,
            messages: [
                { role: 'system', content: { text: systemPrompt } },
                { role: 'user', content: { text: message } }
            ]
        });
        return result.includes('[NOT_IMPROVED]') ? message : result.trim() || message;
    }

    public async sendMessage(opt: MessageSenderTransportOptions): Promise<void> {
        const { context, status, setResponse, setResponseFailed, waitForResponse } = opt;
        const sentMessage = context.message;
        let targetSlug = this.store.active?.slug ?? null;

        if (context.mode.isEdit) {
            if (!targetSlug) {
                setResponseFailed(this.app.translator.__('chat.page.noConversationOpen'));
                return;
            }
            try {
                await this.uploadAttachments(opt);
                if (status.failed) return;
                const state = context.mode.getState('edit');
                const encrypted = await this.store.encryptText(context.message);
                const updated = await this.store.persistMessage(
                    targetSlug,
                    {
                        isAi: false,
                        completion: true,
                        message_id: state.messageId,
                        content: { text: encrypted, attachments: this.attachmentUuids(opt) },
                        __plainText: context.message
                    },
                    true
                );
                this.store.replaceMessage(targetSlug, state.messageId, updated);
                setResponse(null);
            } catch (error) {
                setResponseFailed(this.errorMessage(error));
            }
            return;
        }

        let request: AssistantRequest;
        try {
            request = this.requestFromContext(context);
        } catch (error) {
            setResponseFailed(this.errorMessage(error));
            return;
        }
        let generationStarted = false;
        let conversationCreated = false;
        let provisionalTitle: string | null = null;
        // A regenerate re-runs an existing assistant reply in place: no new user
        // message is shown or persisted, the composer just carries the run's
        // configuration (pre-filled from the message's snapshot by `ChatRegenMode`).
        const optimisticMessage = context.mode.isRegen ? null : this.optimisticUserMessage(opt);

        // A new conversation has no slug/cache entry yet. Let ChatIndex render
        // the message locally while the create request is in flight.
        if (!targetSlug && optimisticMessage) {
            this.options.onConversationPending?.(optimisticMessage);
        }

        try {
            if (!targetSlug) {
                // Title generation is another AI request and can take several
                // seconds. Create the chat immediately with a useful fallback
                // so it never blocks the user's actual message.
                provisionalTitle = this.fallbackTitle(sentMessage);
                targetSlug = (await this.store.create(provisionalTitle, context.systemPrompt, false)).slug;
                conversationCreated = true;
            }

            this.store.beginGeneration(targetSlug);
            generationStarted = true;
            if (optimisticMessage) this.store.appendMessage(targetSlug, optimisticMessage);
            if (conversationCreated) {
                this.options.onConversationCreated?.(targetSlug);
                // Runs concurrently with the assistant response instead of
                // waiting for the stream to finish.
                if (provisionalTitle !== null)
                    void this.generateAndApplyTitle(targetSlug, sentMessage, provisionalTitle);
            }

            await this.uploadAttachments(opt);
            if (status.failed) {
                if (optimisticMessage) this.store.removeCachedMessage(targetSlug, optimisticMessage.message_id);
                this.store.finishGeneration(targetSlug);
                if (conversationCreated) this.options.onConversationPending?.(null);
                return;
            }

            if (!context.mode.isRegen) {
                const encrypted = await this.store.encryptText(sentMessage);
                const userMessage = await this.store.persistMessage(targetSlug, {
                    isAi: false,
                    threadId: request.threadId,
                    completion: true,
                    content: { text: encrypted, attachments: this.attachmentUuids(opt) },
                    __plainText: sentMessage
                });
                if (optimisticMessage) {
                    this.store.replaceMessage(targetSlug, optimisticMessage.message_id, {
                        ...userMessage,
                        clientKey: optimisticMessage.clientKey
                    });
                } else {
                    this.store.appendMessage(targetSlug, userMessage);
                }
            }
        } catch (error) {
            if (optimisticMessage && targetSlug) {
                this.store.removeCachedMessage(targetSlug, optimisticMessage.message_id);
            }
            if (generationStarted && targetSlug) this.store.finishGeneration(targetSlug);
            if (conversationCreated || !targetSlug) this.options.onConversationPending?.(null);
            setResponseFailed(this.errorMessage(error));
            return;
        }

        const conversationSlug = targetSlug;
        waitForResponse(async (response) => {
            try {
                await this.streamAssistant(conversationSlug, request, response);
            } finally {
                this.store.finishGeneration(conversationSlug);
            }
        });
    }

    /**
     * Re-runs the assistant reply `message` and replaces it in place, addressed to the
     * participant of the menu `pick` (see `ChatRegeneratePick`): a `chatSend` handler
     * that claims the pick re-runs the reply with that participant's logic, exactly
     * as a composer send addressed to it would. An unclaimed pick (HAWKI, `null`)
     * stays a plain model rerun from the message's snapshot: the model of the pick
     * (or the one that produced the message), the message's persisted tools and
     * sampling parameters. Tools the resolved model cannot run abort the
     * regeneration with a translated error instead of being silently dropped.
     * `onNotice` reports model fallback. Rejects with the error message on failure.
     */
    public async regenerateMessage(
        conversationSlug: string,
        message: ChatMessage,
        pick: ChatRegeneratePick | null,
        onNotice?: (notice: string) => void
    ): Promise<void> {
        const translator = this.app.translator;
        const requestedModelId = pick?.model ?? message.model;
        const model = this.app.stores.get('ai-models').getModelByIdOrFallback(requestedModelId);
        if (!model) throw new Error(translator.__('chat.tools.unavailable'));

        // The message's snapshot is the base; a claiming handler may replace any of it.
        const storedTools = message.metadata?.tools;
        const tools: string[] = Array.isArray(storedTools) ? storedTools : [];
        const storedParams = message.metadata?.params;
        const params: Record<string, number> = {};
        if (typeof storedParams?.temperature === 'number') params.temperature = storedParams.temperature;
        if (typeof storedParams?.top_p === 'number') params.top_p = storedParams.top_p;

        const send = this.resolveRegenerateDescriptor(message, conversationSlug, pick, {
            model: model.model_id,
            tools,
            params: Object.keys(params).length ? params : null
        });

        const resolvedModel = this.app.stores.get('ai-models').getOneById(send.model);
        if (!resolvedModel) throw new Error(translator.__('chat.tools.unavailable'));
        // The fallback notice concerns the plain path only — a claimed run's model comes from the participant.
        if (!send.assistantHandle && requestedModelId && resolvedModel.model_id !== requestedModelId) {
            onNotice?.(
                translator.__('chat.regenerate.modelNotAvailable', {
                    model: requestedModelId,
                    fallback: resolvedModel.label
                })
            );
        }
        let resolvedTools: string[];
        try {
            resolvedTools = validatedToolSnapshot(send.tools, this.app.stores.get('ai-tools'), resolvedModel);
        } catch (error) {
            throw new Error(this.errorMessage(error));
        }

        const request: AssistantRequest = {
            modelId: resolvedModel.model_id,
            tools: resolvedTools,
            params: send.params,
            systemPrompt:
                (this.store.active?.slug === conversationSlug ? this.store.active.system_prompt : null) ??
                this.app.stores.get('system-prompts').getPromptByType('default').prompt,
            threadId: this.threadIdForMessage(message.message_id),
            regenerateMessageId: message.message_id,
            assistantHandle: send.assistantHandle,
            author: send.author,
            assistant: send.assistant ?? undefined
        };

        this.store.beginGeneration(conversationSlug);
        try {
            const response = new SendMessageResponse();
            const finished = new Promise<void>((resolve, reject) => {
                response.onReceived(() => resolve());
                response.onError((error) => reject(new Error(error)));
            });
            await this.streamAssistant(conversationSlug, request, response);
            await finished;
        } finally {
            this.store.finishGeneration(conversationSlug);
        }
    }

    /** Snapshot of the composer state that shapes the assistant reply for a regular send
     *  or a regenerate (the composer pre-fills its configuration from the message's
     *  snapshot — see `ChatRegenMode`). */
    private requestFromContext(context: ComposerContext): AssistantRequest {
        const regenState = context.mode.isRegen ? context.mode.getState('regen') : null;
        const threadId = regenState
            ? this.threadIdForMessage(regenState.messageId)
            : context.mode.isThread
                ? Number(context.mode.getState('thread').threadId)
                : 0;
        // The resolved run description: the composer's own selection, possibly
        // rewritten by a `chatSend` hook handler (e.g. a plugin pinning model/
        // tools/params to the addressed assistant and binding the conversation).
        const send = this.resolveSendDescriptor(context);
        const model = this.app.stores.get('ai-models').getOneById(send.model);
        if (!model) throw new Error('TOOL_UNAVAILABLE');
        return {
            modelId: model.model_id,
            tools: validatedToolSnapshot(send.tools, this.app.stores.get('ai-tools'), model),
            params: send.params,
            systemPrompt: context.systemPrompt,
            threadId: Number.isFinite(threadId) ? threadId : 0,
            regenerateMessageId: regenState?.messageId ?? null,
            assistantHandle: send.assistantHandle,
            author: send.author,
            assistant: send.assistant ?? undefined
        };
    }

    /**
     * Resolves the run description for one send: the composer's own selection threaded
     * through the `chatSend` hook so plugins can bind the exchange to an addressed
     * assistant and adapt the run to it. The result still passes through the same
     * tool validation as the composer's own selection — a hook may rewrite what gets
     * sent, but never what is allowed.
     */
    private resolveSendDescriptor(context: ComposerContext): ChatSendDescriptor {
        return this.app.hooks.apply(
            'chatSend',
            {
                assistantHandle: null,
                assistant: null,
                author: null,
                model: context.model.current.model_id,
                tools: context.tools.active.map((tool) => tool.toTransferString()),
                params: context.modelParameters.requestParameters
            },
            {
                composer: context,
                conversation: this.store.active,
                regenMessage: null,
                // A participant picked in the model picker's regenerate groups
                // (see `ChatRegenModeState.pickedParticipant`); unclaimed stays
                // a plain model run.
                regenParticipant: context.mode.isRegen
                    ? context.mode.getState('regen').pickedParticipant ?? null
                    : null
            }
        );
    }

    /**
     * Resolves the run description for one regenerate: the message's snapshot as the
     * base, addressed to the picked participant. Mirrors {@link resolveSendDescriptor}
     * minus the composer; the result passes through the same validation.
     */
    private resolveRegenerateDescriptor(
        message: ChatMessage,
        conversationSlug: string,
        pick: ChatRegeneratePick | null,
        base: Pick<ChatSendDescriptor, 'model' | 'tools' | 'params'>
    ): ChatSendDescriptor {
        return this.app.hooks.apply(
            'chatSend',
            {
                assistantHandle: null,
                assistant: null,
                author: null,
                ...base
            },
            {
                composer: null,
                conversation: this.store.active?.slug === conversationSlug ? this.store.active : null,
                regenMessage: message,
                regenParticipant: pick?.participant ?? null
            }
        );
    }

    private optimisticUserMessage(opt: MessageSenderTransportOptions): ChatMessage {
        const connection = this.app.connection;
        if (!connection.isAuthenticated) {
            throw new Error('Current connection is not authenticated');
        }
        const user = connection.userinfo;
        const timestamp = new Date().toISOString();
        const pendingId = `pending-${crypto.randomUUID()}`;
        const threadId = opt.context.mode.isThread ? Number(opt.context.mode.getState('thread').threadId) : 0;

        return {
            threadId: Number.isFinite(threadId) ? threadId : 0,
            author: {
                username: user.username,
                name: user.name,
                avatar_url: this.app.uriBuilder.storageFileUri(user.avatar) ?? ''
            },
            completion: 1,
            content: {
                text: opt.context.message,
                attachments: opt.context.attachments.list.map((file) => ({
                    fileData: {
                        uuid: `pending-${crypto.randomUUID()}`,
                        name: file.name,
                        mime: file.type,
                        type: file.type.startsWith('image/') ? 'image' : 'document',
                        category: 'private',
                        url: ''
                    }
                }))
            },
            created_at: timestamp,
            message_id: pendingId,
            clientKey: pendingId,
            message_role: 'user',
            metadata: { tools: null, params: null },
            model: null,
            updated_at: timestamp,
            isPending: true
        };
    }

    private async uploadAttachments(opt: MessageSenderTransportOptions): Promise<void> {
        await Promise.all(
            opt.context.attachments.list.map(async (file) => {
                if (opt.status.hasFileUuid(file)) return;
                try {
                    opt.status.setFileProgress(file, 0.05);
                    const uuid = await this.store.upload(file);
                    opt.status.setFileUuid(file, uuid);
                    opt.status.setFileProgress(file, 1);
                } catch (error) {
                    opt.status.addFileIssue(file, this.errorMessage(error));
                }
            })
        );
    }

    private attachmentUuids(opt: MessageSenderTransportOptions): string[] {
        return opt.context.attachments.list
            .map((file) => opt.status.getFileUuid(file))
            .filter((uuid): uuid is string => uuid !== null);
    }

    private async streamAssistant(
        conversationSlug: string,
        request: AssistantRequest,
        responseWriter: SendMessageResponse
    ): Promise<void> {
        const controller = new AbortController();
        responseWriter.setAbortController(controller);

        const { threadId, regenerateMessageId } = request;
        const temporaryId = regenerateMessageId ?? `stream-${crypto.randomUUID()}`;
        const existing = regenerateMessageId ? this.store.findMessage(conversationSlug, regenerateMessageId) : null;
        // The replacement follows the request's resolved identity instead of the previous answer's.
        const author = request.author ?? { username: 'HAWKI', name: 'HAWKI', avatar_url: '' };
        const temporary: ChatMessage =
            existing ?
                {
                    ...existing,
                    content: { ...existing.content, text: '' },
                    citations: [],
                    isStreaming: true,
                    status: 'running',
                    author,
                    model: request.modelId
                }
            :   {
                    author,
                    threadId,
                    completion: 0,
                    content: { text: '', attachments: [] },
                    created_at: new Date().toISOString(),
                    message_id: temporaryId,
                    clientKey: temporaryId,
                    message_role: 'assistant',
                    metadata: { tools: null, params: null },
                    model: request.modelId,
                    updated_at: new Date().toISOString(),
                    citations: [],
                    isStreaming: true,
                    status: 'running'
                };
        if (existing) this.store.replaceMessage(conversationSlug, existing.message_id, temporary);
        else this.store.appendMessage(conversationSlug, temporary);

        let text = '';
        let thinking = emptyThinkingTimeline();
        let citations: UrlCitation[] = [];
        let completion = false;
        // Generation metrics for the "Stats for Nerds" experiment. Timing is
        // always collected (it is cheap); the UI decides whether to show it.
        const startedAt = performance.now();
        let firstTokenAt: number | null = null;
        let usage: { promptTokens: number | null; completionTokens: number | null } = {
            promptTokens: null,
            completionTokens: null
        };
        const buildStats = (): MessageStats => {
            const now = performance.now();
            // Output tokens include reasoning tokens (folded in server-side), so the
            // rate uses the whole request time rather than just the visible text phase.
            const totalSeconds = (now - startedAt) / 1000;
            const outputTokens = usage.completionTokens;
            return {
                outputTokens,
                promptTokens: usage.promptTokens,
                tokensPerSecond: outputTokens !== null && totalSeconds > 0 ? outputTokens / totalSeconds : null,
                timeToFirstTokenMs: firstTokenAt === null ? null : Math.round(firstTokenAt - startedAt),
                durationMs: Math.round(now - startedAt)
            };
        };
        try {
            for await (const packet of this.app.aiApi.stream(
                {
                    model: request.modelId,
                    messages: this.messageHistory(
                        conversationSlug,
                        request.systemPrompt,
                        threadId,
                        regenerateMessageId ?? undefined
                    ),
                    tools: request.tools,
                    params: request.params,
                    assistantHandle: request.assistantHandle ?? undefined,
                    threadIndex: threadId,
                    isUpdate: regenerateMessageId !== null,
                    messageId: regenerateMessageId
                },
                { signal: controller.signal }
            )) {
                responseWriter.triggerBodyChunk(JSON.stringify(packet));
                if (packet.type === 'error')
                    throw new Error(String(packet.content ?? this.app.translator.__('chat.page.requestFailed')));
                if (
                    packet.type === 'reasoning_start' ||
                    packet.type === 'reasoning_delta' ||
                    packet.type === 'reasoning_end' ||
                    packet.type === 'provider_tool_event'
                ) {
                    thinking = applyThinkingEvent(thinking, packet);
                    // The stream carries no separate status for thinking; derive it from the events.
                    const status = packet.type === 'reasoning_end' ? 'running' : 'reasoning';
                    this.store.patchMessage(conversationSlug, temporaryId, { reasoning: thinking.parts, status });
                } else if (packet.type === 'status') {
                    const status = typeof packet.status === 'string' ? packet.status : packet.status?.key;
                    this.store.patchMessage(conversationSlug, temporaryId, { status: status ?? 'running' });
                } else if (packet.type === 'message') {
                    const delta = aiPacketText(packet.content);
                    if (delta) firstTokenAt ??= performance.now();
                    text += delta;
                    this.store.patchMessage(conversationSlug, temporaryId, {
                        content: { ...temporary.content, text },
                        stats: buildStats(),
                        ...(delta ? { status: 'generating' } : {})
                    });
                } else if (packet.type === 'citation' && packet.content) {
                    citations = [...citations, packet.content as UrlCitation];
                    this.store.patchMessage(conversationSlug, temporaryId, { citations });
                } else if (packet.type === 'completion') {
                    completion = Boolean(packet.isDone);
                    usage = {
                        promptTokens:
                            typeof packet.usage?.prompt_tokens === 'number' ? packet.usage.prompt_tokens : null,
                        completionTokens:
                            typeof packet.usage?.completion_tokens === 'number' ? packet.usage.completion_tokens : null
                    };
                }
            }
            const stats = buildStats();

            const finalText = text.trim() ? text : this.app.translator.__('chat.page.noResponse');
            const encrypted = await this.store.encryptText(
                JSON.stringify({
                    text: finalText,
                    citations,
                    ...(thinking.parts.length ? { reasoning: thinking.parts } : {}),
                    stats
                })
            );
            const saved = await this.store.persistMessage(
                conversationSlug,
                {
                    isAi: true,
                    ...(regenerateMessageId ? { message_id: regenerateMessageId } : { threadId }),
                    content: { text: encrypted },
                    metadata: {
                        tools: request.tools,
                        params: request.params,
                        // An assistant's display identity, when a `chatSend` handler
                        // supplied one, so the log keeps showing who answered.
                        ...(request.assistant ? { assistant: request.assistant } : {})
                    },
                    model: request.modelId,
                    completion,
                    __plainText: finalText,
                    __citations: citations,
                    __reasoning: thinking.parts.length ? thinking.parts : undefined,
                    __stats: stats
                },
                regenerateMessageId !== null
            );
            // Keep the render key of the streamed message so the keyed list
            // does not remount it when the persisted id replaces the temporary one.
            this.store.replaceMessage(conversationSlug, temporaryId, { ...saved, clientKey: temporaryId });
            responseWriter.triggerReceived();
        } catch (error) {
            if (controller.signal.aborted) {
                if (!existing) this.store.removeCachedMessage(conversationSlug, temporaryId);
                else this.store.replaceMessage(conversationSlug, existing.message_id, existing);
                return;
            }
            if (!existing) this.store.removeCachedMessage(conversationSlug, temporaryId);
            else this.store.replaceMessage(conversationSlug, existing.message_id, existing);
            responseWriter.triggerError(this.errorMessage(error));
        }
    }

    private messageHistory(
        conversationSlug: string,
        systemPrompt: string,
        threadId: number,
        beforeMessageId?: string
    ): AiMessage[] {
        const messages = this.store.messagesFor(conversationSlug);
        const cutoff =
            beforeMessageId ? messages.findIndex((message) => message.message_id === beforeMessageId) : messages.length;
        const selected = messages
            .slice(0, cutoff < 0 ? messages.length : cutoff)
            .filter((message) => {
                const [whole, decimal = 0] = message.message_id.split('.').map(Number);
                return threadId === 0 ? decimal === 0 : whole === threadId;
            })
            .slice(-MESSAGE_HISTORY_CAP);
        return [
            { role: 'system', content: { text: systemPrompt } },
            ...selected
                .filter((message) => !message.isStreaming && !message.isPending && message.content.text.trim())
                .map((message) => ({ role: message.message_role, content: { text: message.content.text } }))
        ];
    }

    private threadIdForMessage(messageId: string): number {
        const [whole, decimal = 0] = messageId.split('.').map(Number);
        return decimal > 0 && Number.isFinite(whole) ? whole : 0;
    }

    private async generateTitle(firstMessage: string): Promise<string> {
        const modelStore = this.app.stores.get('ai-models');
        const promptStore = this.app.stores.get('system-prompts');
        const model =
            modelStore.getSystemModelByType('title_generation') ??
            modelStore.getSystemModelByType('default') ??
            modelStore.models[0];
        if (!model) return this.fallbackTitle(firstMessage);

        try {
            const generatedTitle = await this.app.aiApi.text({
                model: model.model_id,
                messages: [
                    { role: 'system', content: { text: promptStore.getPromptByType('title_generation').prompt } },
                    { role: 'user', content: { text: firstMessage } }
                ]
            });
            return (
                generatedTitle
                    .trim()
                    .replace(/^['"]|['"]$/g, '')
                    .slice(0, 255) || this.fallbackTitle(firstMessage)
            );
        } catch {
            return this.fallbackTitle(firstMessage);
        }
    }

    private async generateAndApplyTitle(slug: string, firstMessage: string, provisionalTitle: string): Promise<void> {
        const title = await this.generateTitle(firstMessage);
        if (title === provisionalTitle || this.store.conversationName(slug) !== provisionalTitle) return;

        try {
            await this.store.rename(slug, title);
        } catch (error) {
            // Naming is best-effort and must never turn a successful message
            // send into an error (the chat keeps its provisional title).
            console.warn('The generated conversation title could not be saved.', error);
        }
    }

    private fallbackTitle(message: string): string {
        const compact = message.replace(/\s+/g, ' ').trim();
        return compact.length > 52 ?
                compact.slice(0, 49) + '…'
            :   compact || this.app.translator.__('chat.page.newChat');
    }

    /** Translates the tool error codes the pre-dispatch validation (and the
     *  stream) can produce; anything else keeps its raw message. */
    private errorMessage(error: unknown): string {
        const code = error instanceof Error ? error.message : null;
        if (code === 'TOOL_ACCESS_DENIED') return this.app.translator.__('chat.tools.accessDenied');
        if (code === 'TOOL_UNAVAILABLE') return this.app.translator.__('chat.tools.unavailable');
        return error instanceof Error ? error.message : this.app.translator.__('chat.page.sendError');
    }
}
