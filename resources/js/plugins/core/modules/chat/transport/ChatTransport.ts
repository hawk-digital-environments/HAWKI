import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {ChatMessage, MessageStats, ReasoningPart} from '$plugins/core/modules/chat/types.js';
import type {MessageSenderTransportInterface, MessageSenderTransportOptions} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import type {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import {aiPacketText} from '$lib/kernel/ai/AiApi.js';
import type {AiMessage, AiStreamRequest} from '$lib/kernel/ai/types.js';
import type {UrlCitation} from '$lib/components/ui/citations/types.js';
import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import {createToolOrCapabilityWithStateFromTransferString} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';

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
}

/** Write surface the streaming loop reports into. `SendMessageResponse` satisfies it for
 *  composer sends; {@link ChatTransport.regenerateMessage} passes a promise adapter. */
interface AssistantStreamSink {
    setAbortController(controller: AbortController): void;
    triggerBodyChunk(chunk: string): void;
    triggerReceived(): void;
    triggerError(error: string): void;
}

export class ChatTransport implements MessageSenderTransportInterface {
    constructor(
        private readonly app: HawkiApp,
        private readonly store: ChatStore,
        private readonly options: ChatTransportOptions = {}
    ) {
    }

    public async improveMessage(message: string, _chatSystemPrompt: string): Promise<string> {
        const models = this.app.stores.get('ai-models');
        const prompts = this.app.stores.get('system-prompts');
        const model = models.getSystemModelByType('prompt_improvement') ?? models.getSystemModelByType('default') ?? models.models[0];
        if (!model) throw new Error(this.app.translator.__('chat.page.noImprovementModel'));

        const systemPrompt = `${prompts.getPromptByType('prompt_improvement').prompt}\n\n` +
            'You are currently in a one-on-one chat. Improve the user message for an AI assistant.\n' +
            `You MUST answer in the language with code: ${this.app.localization.locale.lang}.\n` +
            'Return only the improved message. If it cannot be improved, start the response with [NOT_IMPROVED].';
        const result = await this.app.aiApi.text({
            model: model.model_id,
            messages: [
                {role: 'system', content: {text: systemPrompt}},
                {role: 'user', content: {text: message}}
            ]
        });
        return result.includes('[NOT_IMPROVED]') ? message : (result.trim() || message);
    }

    public async sendMessage(opt: MessageSenderTransportOptions): Promise<void> {
        const {context, status, setResponse, setResponseFailed, waitForResponse} = opt;
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
                const updated = await this.store.persistMessage(targetSlug, {
                    isAi: false,
                    completion: true,
                    message_id: state.messageId,
                    content: {text: encrypted, attachments: this.attachmentUuids(opt)},
                    __plainText: context.message
                }, true);
                this.store.replaceMessage(targetSlug, state.messageId, updated);
                setResponse(null);
            } catch (error) {
                setResponseFailed(this.errorMessage(error));
            }
            return;
        }

        const request = this.requestFromContext(context);
        let generationStarted = false;
        let conversationCreated = false;
        let provisionalTitle: string | null = null;
        const optimisticMessage = this.optimisticUserMessage(opt);

        // A new conversation has no slug/cache entry yet. Let ChatIndex render
        // the message locally while the create request is in flight.
        if (!targetSlug) {
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
            this.store.appendMessage(targetSlug, optimisticMessage);
            if (conversationCreated) this.options.onConversationCreated?.(targetSlug);

            await this.uploadAttachments(opt);
            if (status.failed) {
                this.store.removeCachedMessage(targetSlug, optimisticMessage.message_id);
                this.store.finishGeneration(targetSlug);
                if (conversationCreated) this.options.onConversationPending?.(null);
                return;
            }

            const encrypted = await this.store.encryptText(sentMessage);
            const userMessage = await this.store.persistMessage(targetSlug, {
                isAi: false,
                threadId: request.threadId,
                completion: true,
                content: {text: encrypted, attachments: this.attachmentUuids(opt)},
                __plainText: sentMessage
            });
            this.store.replaceMessage(targetSlug, optimisticMessage.message_id, {...userMessage, clientKey: optimisticMessage.clientKey});
        } catch (error) {
            if (targetSlug) {
                this.store.removeCachedMessage(targetSlug, optimisticMessage.message_id);
            }
            if (generationStarted && targetSlug) this.store.finishGeneration(targetSlug);
            if (conversationCreated || !targetSlug) this.options.onConversationPending?.(null);
            setResponseFailed(this.errorMessage(error));
            return;
        }

        const conversationSlug = targetSlug;
        waitForResponse(async response => {
            try {
                await this.streamAssistant(conversationSlug, request, response);
            } finally {
                this.store.finishGeneration(conversationSlug);
                if (conversationCreated && provisionalTitle !== null) {
                    void this.generateAndApplyTitle(conversationSlug, sentMessage, provisionalTitle);
                }
            }
        });
    }

    /**
     * Re-runs the assistant reply `message` and replaces it in place. `modelId` picks the
     * model; `null` reuses the one that produced the message. A model that is no longer
     * available falls back to the default model. Tools and sampling parameters come from the
     * message's metadata; tools that no longer exist or that the model cannot use are skipped.
     * `onNotice` receives a human-readable line for each such fallback. The conversation is
     * marked as generating for the duration. Rejects with the error message on failure.
     */
    public async regenerateMessage(
        conversationSlug: string,
        message: ChatMessage,
        modelId: string | null,
        onNotice?: (notice: string) => void
    ): Promise<void> {
        const translator = this.app.translator;
        const requestedModelId = modelId ?? message.model;
        const model = this.app.stores.get('ai-models').getModelByIdOrFallback(requestedModelId);
        if (requestedModelId && model.model_id !== requestedModelId) {
            onNotice?.(translator.__('chat.regenerate.modelNotAvailable', {model: requestedModelId, fallback: model.label}));
        }

        const toolStore = this.app.stores.get('ai-tools');
        const tools: string[] = [];
        const storedTools = message.metadata?.tools;
        for (const transferString of Array.isArray(storedTools) ? storedTools : []) {
            if (typeof transferString !== 'string') continue;
            const tool = createToolOrCapabilityWithStateFromTransferString(transferString, toolStore);
            if (!tool) {
                onNotice?.(translator.__('chat.regenerate.toolNotAvailable', {tool: transferString}));
                continue;
            }
            if (!tool.isAvailableFor(model)) {
                onNotice?.(translator.__('chat.regenerate.toolNotAvailableForModel', {tool: tool.name}));
                continue;
            }
            tools.push(tool.toTransferString());
        }

        // Only the sampling parameters travel along; everything else falls back to the
        // model's defaults so a different model is not sent settings it does not know.
        const storedParams = message.metadata?.params;
        const params: Record<string, number> = {};
        if (typeof storedParams?.temperature === 'number') params.temperature = storedParams.temperature;
        if (typeof storedParams?.top_p === 'number') params.top_p = storedParams.top_p;

        const request: AssistantRequest = {
            modelId: model.model_id,
            tools,
            params: Object.keys(params).length ? params : null,
            systemPrompt: (this.store.active?.slug === conversationSlug ? this.store.active.system_prompt : null)
                ?? this.app.stores.get('system-prompts').getPromptByType('default').prompt,
            threadId: this.threadIdForMessage(message.message_id),
            regenerateMessageId: message.message_id
        };

        this.store.beginGeneration(conversationSlug);
        try {
            await new Promise<void>((resolve, reject) => {
                this.streamAssistant(conversationSlug, request, {
                    setAbortController: () => undefined,
                    triggerBodyChunk: () => undefined,
                    triggerReceived: () => resolve(),
                    triggerError: error => reject(new Error(error))
                }).then(resolve, reject);
            });
        } finally {
            this.store.finishGeneration(conversationSlug);
        }
    }

    /** Snapshot of the composer state that shapes the assistant reply for a regular send. */
    private requestFromContext(context: ComposerContext): AssistantRequest {
        const threadId = context.mode.isThread ? Number(context.mode.getState('thread').threadId) : 0;
        return {
            modelId: context.model.current.model_id,
            tools: context.tools.active.map(tool => tool.toTransferString()),
            params: context.modelParameters.requestParameters,
            systemPrompt: context.systemPrompt,
            threadId: Number.isFinite(threadId) ? threadId : 0,
            regenerateMessageId: null
        };
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
                attachments: opt.context.attachments.list.map(file => ({
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
            metadata: {tools: null, params: null},
            model: null,
            updated_at: timestamp,
            isPending: true
        };
    }

    private async uploadAttachments(opt: MessageSenderTransportOptions): Promise<void> {
        await Promise.all(opt.context.attachments.list.map(async file => {
            if (opt.status.hasFileUuid(file)) return;
            try {
                opt.status.setFileProgress(file, 0.05);
                const uuid = await this.store.upload(file);
                opt.status.setFileUuid(file, uuid);
                opt.status.setFileProgress(file, 1);
            } catch (error) {
                opt.status.addFileIssue(file, this.errorMessage(error));
            }
        }));
    }

    private attachmentUuids(opt: MessageSenderTransportOptions): string[] {
        return opt.context.attachments.list
            .map(file => opt.status.getFileUuid(file))
            .filter((uuid): uuid is string => uuid !== null);
    }

    private async streamAssistant(conversationSlug: string, request: AssistantRequest, responseWriter: AssistantStreamSink): Promise<void> {
        const controller = new AbortController();
        responseWriter.setAbortController(controller);

        const {threadId, regenerateMessageId} = request;
        const temporaryId = regenerateMessageId ?? `stream-${crypto.randomUUID()}`;
        const existing = regenerateMessageId ? this.store.findMessage(conversationSlug, regenerateMessageId) : null;
        const temporary: ChatMessage = existing ? {
            ...existing,
            content: {...existing.content, text: ''},
            citations: [],
            isStreaming: true,
            status: 'running'
        } : {
            author: {username: 'HAWKI', name: 'HAWKI', avatar_url: ''},
            threadId,
            completion: 0,
            content: {text: '', attachments: []},
            created_at: new Date().toISOString(),
            message_id: temporaryId,
            clientKey: temporaryId,
            message_role: 'assistant',
            metadata: {tools: null, params: null},
            model: request.modelId,
            updated_at: new Date().toISOString(),
            citations: [],
            isStreaming: true,
            status: 'running'
        };
        if (existing) this.store.replaceMessage(conversationSlug, existing.message_id, temporary);
        else this.store.appendMessage(conversationSlug, temporary);

        let text = '';
        let reasoning: ReasoningPart[] = [];
        let citations: UrlCitation[] = [];
        let completion = false;
        // Generation metrics for the "Stats for Nerds" experiment. Timing is
        // always collected (it is cheap); the UI decides whether to show it.
        const startedAt = performance.now();
        let firstTokenAt: number | null = null;
        let usage: {promptTokens: number | null; completionTokens: number | null} = {promptTokens: null, completionTokens: null};
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
            for await (const packet of this.app.aiApi.stream({
                model: request.modelId,
                messages: this.messageHistory(conversationSlug, request.systemPrompt, threadId, regenerateMessageId ?? undefined),
                tools: request.tools,
                params: request.params,
                threadIndex: threadId,
                isUpdate: regenerateMessageId !== null,
                messageId: regenerateMessageId
            }, {signal: controller.signal})) {
                responseWriter.triggerBodyChunk(JSON.stringify(packet));
                if (packet.type === 'error') throw new Error(String(packet.content ?? this.app.translator.__('chat.page.requestFailed')));
                if (packet.type === 'status') {
                    const status = typeof packet.status === 'string' ? packet.status : packet.status?.key;
                    const value = typeof packet.status === 'object' ? packet.status?.value : undefined;
                    if (status === 'reasoning_delta' && typeof value === 'string') {
                        const last = reasoning.at(-1);
                        reasoning = last?.type === 'text'
                            ? [...reasoning.slice(0, -1), {type: 'text', text: last.text + value}]
                            : [...reasoning, {type: 'text', text: value}];
                        this.store.patchMessage(conversationSlug, temporaryId, {status, reasoning});
                    } else if (status === 'web_search' && value && typeof value === 'object') {
                        const search = value as {type?: unknown; query?: unknown; sources?: unknown};
                        reasoning = [...reasoning, {
                            type: 'web_search',
                            action: typeof search.type === 'string' ? search.type : 'search',
                            query: typeof search.query === 'string' ? search.query : null,
                            sources: Array.isArray(search.sources) ? search.sources.filter((url): url is string => typeof url === 'string') : []
                        }];
                        this.store.patchMessage(conversationSlug, temporaryId, {status, reasoning});
                    } else {
                        this.store.patchMessage(conversationSlug, temporaryId, {status: status ?? 'running'});
                    }
                } else if (packet.type === 'message') {
                    const delta = aiPacketText(packet.content);
                    if (delta) firstTokenAt ??= performance.now();
                    text += delta;
                    this.store.patchMessage(conversationSlug, temporaryId, {content: {...temporary.content, text}, stats: buildStats()});
                } else if (packet.type === 'citation' && packet.content) {
                    citations = [...citations, packet.content as UrlCitation];
                    this.store.patchMessage(conversationSlug, temporaryId, {citations});
                } else if (packet.type === 'completion') {
                    completion = Boolean(packet.isDone);
                    usage = {
                        promptTokens: typeof packet.usage?.prompt_tokens === 'number' ? packet.usage.prompt_tokens : null,
                        completionTokens: typeof packet.usage?.completion_tokens === 'number' ? packet.usage.completion_tokens : null
                    };
                }
            }
            const stats = buildStats();

            const finalText = text.trim() ? text : this.app.translator.__('chat.page.noResponse');
            const encrypted = await this.store.encryptText(JSON.stringify({text: finalText, citations, ...(reasoning.length ? {reasoning} : {}), stats}));
            const saved = await this.store.persistMessage(conversationSlug, {
                isAi: true,
                ...(regenerateMessageId ? {message_id: regenerateMessageId} : {threadId}),
                content: {text: encrypted},
                metadata: {
                    tools: request.tools,
                    params: request.params
                },
                model: request.modelId,
                completion,
                __plainText: finalText,
                __citations: citations,
                __reasoning: reasoning.length ? reasoning : undefined,
                __stats: stats
            }, regenerateMessageId !== null);
            // Keep the render key of the streamed message so the keyed list
            // does not remount it when the persisted id replaces the temporary one.
            this.store.replaceMessage(conversationSlug, temporaryId, {...saved, clientKey: temporaryId});
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

    private messageHistory(conversationSlug: string, systemPrompt: string, threadId: number, beforeMessageId?: string): AiMessage[] {
        const messages = this.store.messagesFor(conversationSlug);
        const cutoff = beforeMessageId ? messages.findIndex(message => message.message_id === beforeMessageId) : messages.length;
        const selected = messages
            .slice(0, cutoff < 0 ? messages.length : cutoff)
            .filter(message => {
                const [whole, decimal = 0] = message.message_id.split('.').map(Number);
                return threadId === 0 ? decimal === 0 : whole === threadId;
            })
            .slice(-20);
        return [
            {role: 'system', content: {text: systemPrompt}},
            ...selected
                .filter(message => !message.isStreaming && message.content.text.trim())
                .map(message => ({role: message.message_role, content: {text: message.content.text}}))
        ];
    }

    private threadIdForMessage(messageId: string): number {
        const [whole, decimal = 0] = messageId.split('.').map(Number);
        return decimal > 0 && Number.isFinite(whole) ? whole : 0;
    }

    private async generateTitle(firstMessage: string): Promise<string> {
        const modelStore = this.app.stores.get('ai-models');
        const promptStore = this.app.stores.get('system-prompts');
        const model = modelStore.getSystemModelByType('title_generation') ?? modelStore.getSystemModelByType('default') ?? modelStore.models[0];
        const prompt = promptStore.getPromptByType('title_generation').prompt;
        if (!model) return this.fallbackTitle(firstMessage);

        try {
            const generatedTitle = await this.app.aiApi.text({
                model: model.model_id,
                messages: [
                    {role: 'system', content: {text: prompt}},
                    {role: 'user', content: {text: firstMessage}}
                ]
            });
            return generatedTitle.trim().replace(/^['"]|['"]$/g, '').slice(0, 255) || this.fallbackTitle(firstMessage);
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
        return compact.length > 52 ? compact.slice(0, 49) + '…' : compact || this.app.translator.__('chat.page.newChat');
    }

    private errorMessage(error: unknown): string {
        return error instanceof Error ? error.message : this.app.translator.__('chat.page.sendError');
    }
}
