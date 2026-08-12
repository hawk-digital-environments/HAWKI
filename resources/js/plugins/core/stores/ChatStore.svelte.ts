import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {decryptSymmetric, encryptSymmetric, loadSymmetricCryptoValueFromObject} from '$lib/kernel/encryption/symmetric.js';
import type {OldUiConversationMessage} from '$lib/legacy/OldUiBridge.svelte.js';
import {chatJson, chatRequest} from '$plugins/core/modules/chat/api.js';
import type {ChatConversation, ChatMessage, ChatSummary, EncryptedText} from '$plugins/core/modules/chat/types.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        chat: ChatStore;
    }
}

type ConversationResponse = {success: boolean; data: any};
type MessageResponse = {success: boolean; messageData: OldUiConversationMessage; legacyResource?: OldUiConversationMessage};

function decodeLegacyHtml(value: string): string {
    if (!value.includes('&')) return value;
    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;
    return textarea.value;
}

export class ChatStore implements DataStore {
    public readonly name = 'chat';

    public conversations = $state<ChatSummary[]>([]);
    public active = $state<ChatConversation | null>(null);
    public loading = $state(false);
    public listLoading = $state(false);
    public error = $state<string | null>(null);
    /** Conversation slugs with an AI request still running in this tab. */
    public generatingSlugs = $state<string[]>([]);

    private app: HawkiApp | null = null;
    private activeLoad = 0;
    /** Keeps in-flight conversations alive when another chat becomes active. */
    private conversationCache = new Map<string, ChatConversation>();

    public async loadData(app: HawkiApp): Promise<void> {
        this.app = app;
        try {
            app.authenticatedConnection;
        } catch {
            return;
        }
        await this.refresh();
    }

    public async refresh(): Promise<void> {
        this.listLoading = true;
        try {
            const response = await chatRequest<{success: boolean; data: ChatSummary[]}>('/req/conv');
            this.conversations = response.data;
        } finally {
            this.listLoading = false;
        }
    }

    public startNew(): void {
        this.activeLoad += 1;
        this.active = null;
        this.loading = false;
        this.error = null;
    }

    public async load(slug: string): Promise<ChatConversation> {
        const requestId = ++this.activeLoad;
        const cached = this.conversationCache.get(slug);
        if (cached && this.isGenerating(slug)) {
            this.active = cached;
            this.conversationCache.set(slug, this.active);
            this.loading = false;
            this.error = null;
            return this.active;
        }

        this.loading = true;
        this.error = null;
        try {
            const key = await this.conversationKey();
            const response = await chatRequest<ConversationResponse>(`/req/conv/${encodeURIComponent(slug)}`);
            const source = response.data;
            const conversation: ChatConversation = {
                id: source.id,
                name: source.name,
                slug: source.slug,
                system_prompt: await this.decryptText(source.system_prompt, key),
                messages: await Promise.all((source.messages ?? []).map((message: OldUiConversationMessage) => this.decryptMessage(message, key)))
            };
            if (requestId === this.activeLoad) {
                // A generation may have started while this request was loading. Its
                // cached copy is newer than the server response until it is persisted.
                const inFlight = this.isGenerating(slug) ? this.conversationCache.get(slug) : null;
                this.active = inFlight ?? conversation;
                this.conversationCache.set(slug, this.active);
            } else {
                this.conversationCache.set(slug, conversation);
            }
            return conversation;
        } catch (error) {
            if (requestId === this.activeLoad) this.error = this.errorMessage(error);
            throw error;
        } finally {
            if (requestId === this.activeLoad) this.loading = false;
        }
    }

    public async create(name: string, systemPrompt: string, activate = true): Promise<ChatConversation> {
        const encryptedPrompt = await this.encryptText(systemPrompt);
        const response = await chatRequest<{success: boolean; conv: any}>('/req/conv/createChat', chatJson('POST', {
            conv_name: name,
            system_prompt: JSON.stringify(encryptedPrompt)
        }));
        const conversation: ChatConversation = {
            id: response.conv.id,
            name,
            slug: response.conv.slug,
            system_prompt: systemPrompt,
            messages: []
        };
        if (activate) {
            this.active = conversation;
            this.conversationCache.set(conversation.slug, this.active);
        } else {
            this.conversationCache.set(conversation.slug, conversation);
        }
        this.upsertSummary(conversation);
        return conversation;
    }

    public async rename(slug: string, name: string): Promise<void> {
        await chatRequest(`/req/conv/updateInfo/${encodeURIComponent(slug)}`, chatJson('POST', {conv_name: name}));
        const conversation = this.getConversation(slug);
        if (conversation) conversation.name = name;
        const summary = this.conversations.find(item => item.slug === slug);
        if (summary) summary.name = name;
    }

    public conversationName(slug: string): string | null {
        return this.getConversation(slug)?.name
            ?? this.conversations.find(item => item.slug === slug)?.name
            ?? null;
    }

    public async updateSystemPrompt(slug: string, prompt: string): Promise<void> {
        const encrypted = await this.encryptText(prompt);
        await chatRequest(`/req/conv/updateInfo/${encodeURIComponent(slug)}`, chatJson('POST', {
            system_prompt: JSON.stringify(encrypted)
        }));
        if (this.active?.slug === slug) this.active.system_prompt = prompt;
    }

    public async remove(slug: string): Promise<void> {
        await chatRequest(`/req/conv/removeConv/${encodeURIComponent(slug)}`, chatJson('DELETE'));
        this.conversations = this.conversations.filter(item => item.slug !== slug);
        this.conversationCache.delete(slug);
        if (this.active?.slug === slug) this.active = null;
    }

    public async removeMessage(messageId: string): Promise<void> {
        if (!this.active) return;
        const slug = this.active.slug;
        await chatRequest(`/req/conv/message/delete/${encodeURIComponent(slug)}`, chatJson('DELETE', {
            message_id: messageId
        }));
        this.removeCachedMessage(slug, messageId);
    }

    public async removeAttachment(messageId: string, fileId: string): Promise<void> {
        const slug = this.active?.slug;
        if (!slug) return;
        await chatRequest('/req/conv/attachment/delete', chatJson('DELETE', {fileId}));
        const conversation = this.getConversation(slug);
        if (!conversation) return;
        conversation.messages = conversation.messages.map(message => message.message_id !== messageId ? message : ({
            ...message,
            content: {
                ...message.content,
                attachments: message.content.attachments.filter(attachment => attachment.fileData.uuid !== fileId)
            }
        }));
    }

    public appendMessage(slug: string, message: ChatMessage): void {
        const conversation = this.getConversation(slug);
        if (!conversation) return;
        conversation.messages = [...conversation.messages, message];
        this.touch(slug);
    }

    public replaceMessage(slug: string, messageId: string, message: ChatMessage): void {
        const conversation = this.getConversation(slug);
        if (!conversation) return;
        conversation.messages = conversation.messages.map(current => current.message_id === messageId ? message : current);
    }

    public patchMessage(slug: string, messageId: string, patch: Partial<ChatMessage>): void {
        const conversation = this.getConversation(slug);
        if (!conversation) return;
        conversation.messages = conversation.messages.map(message => message.message_id === messageId ? {...message, ...patch} : message);
    }

    public findMessage(slug: string, messageId: string): ChatMessage | null {
        return this.getConversation(slug)?.messages.find(message => message.message_id === messageId) ?? null;
    }

    public messagesFor(slug: string): ChatMessage[] {
        return this.getConversation(slug)?.messages ?? [];
    }

    public removeCachedMessage(slug: string, messageId: string): void {
        const conversation = this.getConversation(slug);
        if (!conversation) return;
        conversation.messages = conversation.messages.filter(message => message.message_id !== messageId);
    }

    public async persistMessage(slug: string, payload: Record<string, unknown>, update = false): Promise<ChatMessage> {
        if (!this.getConversation(slug)) throw new Error('The target conversation is unavailable.');
        const action = update ? 'updateMessage' : 'sendMessage';
        const {__plainText, __citations, ...requestPayload} = payload;
        const response = await chatRequest<MessageResponse>(
            `/req/conv/${action}/${encodeURIComponent(slug)}`,
            chatJson('POST', requestPayload)
        );
        const resource = response.legacyResource ?? response.messageData;
        return this.normalisePlainMessage(resource, __plainText as string | undefined, __citations as any[] | undefined);
    }

    public isGenerating(slug: string | null | undefined): boolean {
        return Boolean(slug && this.generatingSlugs.includes(slug));
    }

    public beginGeneration(slug: string): void {
        if (this.active?.slug === slug) this.conversationCache.set(slug, this.active);
        if (!this.generatingSlugs.includes(slug)) {
            this.generatingSlugs = [...this.generatingSlugs, slug];
        }
    }

    public finishGeneration(slug: string): void {
        this.generatingSlugs = this.generatingSlugs.filter(current => current !== slug);
    }

    public async encryptText(value: string): Promise<EncryptedText> {
        const key = await this.conversationKey();
        return (await encryptSymmetric(value, key)).toObject();
    }

    public async upload(file: File, signal?: AbortSignal): Promise<string> {
        const form = new FormData();
        form.append('file', file);
        const response = await chatRequest<{success: boolean; uuid: string}>('/req/conv/attachment/upload', {
            method: 'POST',
            body: form,
            signal
        });
        return response.uuid;
    }

    private async conversationKey(): Promise<CryptoKey> {
        if (!this.app) throw new Error('The chat store has not been initialised.');
        const keychain = this.app.stores.get('keychain');
        await keychain.waitingToLoad;
        if (!keychain.aiConvKey) throw new Error('The encrypted chat key is unavailable. Please sign in again.');
        return keychain.aiConvKey;
    }

    private async decryptText(value: string | EncryptedText, key: CryptoKey): Promise<string> {
        const encrypted = typeof value === 'string' ? JSON.parse(value) : value;
        return decryptSymmetric(loadSymmetricCryptoValueFromObject(encrypted), key);
    }

    private async decryptMessage(source: OldUiConversationMessage, key: CryptoKey): Promise<ChatMessage> {
        const encrypted = source.content.text as unknown as EncryptedText;
        const raw = await decryptSymmetric(loadSymmetricCryptoValueFromObject(encrypted), key);
        let text = raw;
        let citations: any[] = [];
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && typeof parsed.text === 'string') {
                text = parsed.text;
                citations = Array.isArray(parsed.citations) ? parsed.citations : [];
            }
        } catch {
            // User messages in the legacy format are plain encrypted strings.
        }
        return {
            ...source,
            content: {...source.content, text: decodeLegacyHtml(text)},
            citations
        };
    }

    private normalisePlainMessage(source: OldUiConversationMessage, text?: string, citations: any[] = []): ChatMessage {
        return {
            ...source,
            content: {...source.content, text: text ?? ''},
            citations
        };
    }

    private upsertSummary(conversation: ChatConversation): void {
        const current = this.conversations.filter(item => item.slug !== conversation.slug);
        this.conversations = [{
            id: conversation.id,
            name: conversation.name,
            slug: conversation.slug,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
        }, ...current];
    }

    private getConversation(slug: string): ChatConversation | null {
        if (this.active?.slug === slug) {
            this.conversationCache.set(slug, this.active);
            return this.active;
        }
        return this.conversationCache.get(slug) ?? null;
    }

    private touch(slug: string): void {
        const summary = this.conversations.find(item => item.slug === slug);
        if (!summary) return;
        summary.updated_at = new Date().toISOString();
        this.conversations = [summary, ...this.conversations.filter(item => item.slug !== summary.slug)];
    }

    private errorMessage(error: unknown): string {
        return error instanceof Error ? error.message : 'The conversation could not be loaded.';
    }
}
