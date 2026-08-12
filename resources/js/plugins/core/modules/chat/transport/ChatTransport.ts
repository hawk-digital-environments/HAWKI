import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {ChatMessage} from '$plugins/core/modules/chat/types.js';
import type {MessageSenderTransportInterface, MessageSenderTransportOptions} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import type {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import {aiPacketText} from '$lib/kernel/ai/AiApi.js';
import type {AiMessage} from '$lib/kernel/ai/types.js';

interface ChatTransportOptions {
    onConversationCreated?: (slug: string) => void;
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
        if (!model) throw new Error('No model is available to improve the message.');

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
                setResponseFailed('No conversation is open.');
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

        let generationStarted = false;
        let conversationCreated = false;
        let provisionalTitle: string | null = null;
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
            if (conversationCreated) this.options.onConversationCreated?.(targetSlug);

            await this.uploadAttachments(opt);
            if (status.failed) {
                this.store.finishGeneration(targetSlug);
                return;
            }

            if (!context.mode.isRegen) {
                const threadId = context.mode.isThread ? Number(context.mode.getState('thread').threadId) : 0;
                const encrypted = await this.store.encryptText(sentMessage);
                const userMessage = await this.store.persistMessage(targetSlug, {
                    isAi: false,
                    threadId: Number.isFinite(threadId) ? threadId : 0,
                    completion: true,
                    content: {text: encrypted, attachments: this.attachmentUuids(opt)},
                    __plainText: sentMessage
                });
                this.store.appendMessage(targetSlug, userMessage);
            }
        } catch (error) {
            if (generationStarted && targetSlug) this.store.finishGeneration(targetSlug);
            setResponseFailed(this.errorMessage(error));
            return;
        }

        const conversationSlug = targetSlug;
        waitForResponse(async response => {
            try {
                await this.streamAssistant(conversationSlug, opt, response);
            } finally {
                this.store.finishGeneration(conversationSlug);
                if (conversationCreated && provisionalTitle !== null) {
                    void this.generateAndApplyTitle(conversationSlug, sentMessage, provisionalTitle);
                }
            }
        });
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

    private async streamAssistant(conversationSlug: string, opt: MessageSenderTransportOptions, responseWriter: any): Promise<void> {
        const {context} = opt;
        const controller = new AbortController();
        responseWriter.setAbortController(controller);

        const regenState = context.mode.isRegen ? context.mode.getState('regen') : null;
        const threadId = context.mode.isThread
            ? Number(context.mode.getState('thread').threadId)
            : (regenState ? this.threadIdForMessage(regenState.messageId) : 0);
        const temporaryId = regenState?.messageId ?? `stream-${crypto.randomUUID()}`;
        const existing = regenState ? this.store.findMessage(conversationSlug, regenState.messageId) : null;
        const temporary: ChatMessage = existing ? {
            ...existing,
            content: {...existing.content, text: ''},
            citations: [],
            isStreaming: true,
            status: 'running'
        } : {
            author: {username: 'HAWKI', name: 'HAWKI', avatar_url: ''},
            completion: 0,
            content: {text: '', attachments: []},
            created_at: new Date().toISOString(),
            message_id: temporaryId,
            message_role: 'assistant',
            metadata: {tools: null, params: null},
            model: context.model.current?.model_id ?? null,
            updated_at: new Date().toISOString(),
            citations: [],
            isStreaming: true,
            status: 'running'
        };
        if (existing) this.store.replaceMessage(conversationSlug, existing.message_id, temporary);
        else this.store.appendMessage(conversationSlug, temporary);

        let text = '';
        let citations: any[] = [];
        let completion = false;
        try {
            for await (const packet of this.app.aiApi.stream({
                model: context.model.current.model_id,
                messages: this.messageHistory(conversationSlug, context.systemPrompt, threadId, regenState?.messageId),
                tools: context.tools.active.map(tool => tool.toTransferString()),
                params: context.modelParameters.list,
                threadIndex: Number.isFinite(threadId) ? threadId : 0,
                isUpdate: Boolean(regenState),
                messageId: regenState?.messageId ?? null
            }, {signal: controller.signal})) {
                responseWriter.triggerBodyChunk(JSON.stringify(packet));
                if (packet.type === 'error') throw new Error(String(packet.content ?? 'The AI request failed.'));
                if (packet.type === 'status') {
                    const status = typeof packet.status === 'string' ? packet.status : packet.status?.key;
                    this.store.patchMessage(conversationSlug, temporaryId, {status: status ?? 'running'});
                } else if (packet.type === 'message') {
                    text += aiPacketText(packet.content);
                    this.store.patchMessage(conversationSlug, temporaryId, {content: {...temporary.content, text}});
                } else if (packet.type === 'citation' && packet.content) {
                    citations = [...citations, packet.content];
                    this.store.patchMessage(conversationSlug, temporaryId, {citations});
                } else if (packet.type === 'completion') {
                    completion = Boolean(packet.isDone);
                }
            }

            const finalText = text.trim() ? text : 'The model returned no response.';
            const encrypted = await this.store.encryptText(JSON.stringify({text: finalText, citations}));
            const saved = await this.store.persistMessage(conversationSlug, {
                isAi: true,
                ...(regenState ? {message_id: regenState.messageId} : {threadId: Number.isFinite(threadId) ? threadId : 0}),
                content: {text: encrypted},
                metadata: {
                    tools: context.tools.active.map(tool => tool.toTransferString()),
                    params: context.modelParameters.list
                },
                model: context.model.current.model_id,
                completion,
                __plainText: finalText,
                __citations: citations
            }, Boolean(regenState));
            this.store.replaceMessage(conversationSlug, temporaryId, saved);
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
        return compact.length > 52 ? compact.slice(0, 49) + '…' : compact || 'New chat';
    }

    private errorMessage(error: unknown): string {
        return error instanceof Error ? error.message : 'The message could not be sent.';
    }
}
