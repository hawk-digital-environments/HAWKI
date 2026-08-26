import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {decryptSymmetric, encryptSymmetric, loadSymmetricCryptoValue, loadSymmetricCryptoValueFromObject} from '$lib/kernel/encryption/symmetric.js';
import {decodeJsonApiResourceResponse} from '$lib/kernel/api/jsonApiEncoding.js';
import AiConvMessageSchema, {type AiConvMessage} from '$plugins/core/schemas/resources/ai-conv-messages.schema.js';
import type {ChatConversation, ChatMessage, ChatSummary, EncryptedText, ReasoningPart} from '$plugins/core/modules/chat/types.js';
import type {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';
import type {UrlCitation} from '$lib/components/ui/citations/types.js';

type ChatStoreDependencies = {
    restApi: HawkiApp['restApi'];
    uriBuilder: HawkiApp['uriBuilder'];
    keychain: KeychainStore;
    translator: HawkiApp['translator'];
};

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        chat: ChatStore;
    }
}

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

    private _dependencies: ChatStoreDependencies | null = null;
    private activeLoad = 0;
    /** Keeps in-flight conversations alive when another chat becomes active. */
    private conversationCache = new Map<string, ChatConversation>();

    public async loadData(app: HawkiApp): Promise<void> {
        this._dependencies = {
            restApi: app.restApi,
            uriBuilder: app.uriBuilder,
            keychain: app.stores.get('keychain'),
            translator: app.translator
        };
        if (!app.connection.isAuthenticated) {
            return;
        }
        await this.refresh();
    }

    public async refresh(): Promise<void> {
        this.listLoading = true;
        try {
            const conversations = [];
            let page = 1;

            while (true) {
                const collection = await this.dependencies.restApi.getResourceCollection('ai-convs', {
                    query: {page: {number: page}}
                });
                conversations.push(...collection);

                if (!collection._pagination?.hasNextPage) break;
                page += 1;
            }

            this.conversations = conversations.map(conversation => ({
                name: conversation.name,
                slug: conversation.slug,
                created_at: conversation.created_at,
                updated_at: conversation.updated_at
            }));
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
            const source = await this.dependencies.restApi.getResource('ai-convs', slug, {
                query: {include: 'messages.author,messages.attachments'}
            });
            const conversation: ChatConversation = {
                name: source.name,
                slug: source.slug,
                system_prompt: source.system_prompt ? await this.decryptText(source.system_prompt, key) : '',
                messages: await Promise.all((source.messages ?? []).map(message => this.decryptMessage(message, key)))
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
        const resource = await this.dependencies.restApi.createResource('ai-convs', {
            name,
            system_prompt: JSON.stringify(encryptedPrompt)
        });
        const conversation: ChatConversation = {
            name,
            slug: resource.slug,
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
        await this.dependencies.restApi.updateResource('ai-convs', slug, {name});
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
        await this.dependencies.restApi.updateResource('ai-convs', slug, {
            system_prompt: JSON.stringify(encrypted)
        });
        if (this.active?.slug === slug) this.active.system_prompt = prompt;
    }

    public async remove(slug: string): Promise<void> {
        await this.dependencies.restApi.deleteResource('ai-convs', slug);
        this.conversations = this.conversations.filter(item => item.slug !== slug);
        this.conversationCache.delete(slug);
        if (this.active?.slug === slug) this.active = null;
    }

    public async removeMessage(messageId: string): Promise<void> {
        if (!this.active) return;
        const slug = this.active.slug;
        await this.dependencies.restApi.deleteFromResourceAction(
            'ai-convs',
            `${encodeURIComponent(slug)}/actions/messages/${encodeURIComponent(messageId)}`
        );
        this.removeCachedMessage(slug, messageId);
    }

    public async removeAttachment(messageId: string, fileId: string): Promise<void> {
        const slug = this.active?.slug;
        if (!slug) return;
        await this.dependencies.restApi.deleteFromResourceAction(
            'ai-convs',
            `actions/attachments/${encodeURIComponent(fileId)}`
        );
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
        const {__plainText, __citations, __reasoning, message_id, ...requestPayload} = payload;
        const response = update
            ? await this.dependencies.restApi.patchToResourceAction(
                'ai-convs',
                `${encodeURIComponent(slug)}/actions/messages/${encodeURIComponent(String(message_id))}`,
                requestPayload
            )
            : await this.dependencies.restApi.postToResourceAction(
                'ai-convs',
                `${encodeURIComponent(slug)}/actions/messages`,
                requestPayload
            );
        const resource = AiConvMessageSchema.parse(decodeJsonApiResourceResponse(response));
        return this.normalisePlainMessage(resource, __plainText as string | undefined, __citations as UrlCitation[] | undefined, __reasoning as ReasoningPart[] | undefined);
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
        const response = await this.dependencies.restApi.postToResourceAction('ai-convs', 'actions/attachments', form, {signal});
        return response.uuid;
    }

    private async conversationKey(): Promise<CryptoKey> {
        const keychain = this.dependencies.keychain;
        await keychain.waitingToLoad;
        if (!keychain.aiConvKey) throw new Error('The encrypted chat key is unavailable. Please sign in again.');
        return keychain.aiConvKey;
    }

    private async decryptText(value: string | EncryptedText, key: CryptoKey): Promise<string> {
        const encrypted = typeof value === 'string' ? JSON.parse(value) : value;
        return decryptSymmetric(loadSymmetricCryptoValueFromObject(encrypted), key);
    }

    private async decryptMessage(source: AiConvMessage, key: CryptoKey): Promise<ChatMessage> {
        const raw = await decryptSymmetric(loadSymmetricCryptoValue(source.content), key);
        let text = raw;
        let citations: UrlCitation[] = [];
        let reasoning: ReasoningPart[] | undefined;
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && typeof parsed.text === 'string') {
                text = parsed.text;
                citations = Array.isArray(parsed.citations) ? parsed.citations : [];
                reasoning = Array.isArray(parsed.reasoning) && parsed.reasoning.length
                    ? parsed.reasoning
                    : (typeof parsed.reasoning === 'string' && parsed.reasoning ? [{type: 'text', text: parsed.reasoning}] : undefined);
            }
        } catch {
            // User messages in the legacy format are plain encrypted strings.
        }
        return this.toChatMessage(source, decodeLegacyHtml(text), citations, reasoning);
    }

    private normalisePlainMessage(source: AiConvMessage, text?: string, citations: UrlCitation[] = [], reasoning?: ReasoningPart[]): ChatMessage {
        return this.toChatMessage(source, text ?? '', citations, reasoning);
    }

    private toChatMessage(source: AiConvMessage, text: string, citations: UrlCitation[], reasoning?: ReasoningPart[]): ChatMessage {
        return {
            author: {
                username: source.author.username,
                name: source.author.name,
                avatar_url: source.author.avatar ? (this.dependencies.uriBuilder.storageFileUri(source.author.avatar) ?? '') : ''
            },
            completion: source.completion ? 1 : 0,
            content: {
                text,
                attachments: source.attachments.map(attachment => ({
                    fileData: {
                        uuid: attachment.uuid,
                        name: attachment.name,
                        mime: attachment.mime,
                        type: attachment.type,
                        category: attachment.category,
                        url: this.dependencies.uriBuilder.storageFileUri(attachment.identifier) ?? ''
                    }
                }))
            },
            created_at: source.created_at ?? '',
            message_id: source.message_id,
            message_role: source.message_role,
            metadata: {
                tools: (source.metadata?.tools ?? null) as Record<string, unknown> | null,
                params: (source.metadata?.params ?? null) as Record<string, unknown> | null
            },
            model: source.model,
            updated_at: source.updated_at ?? '',
            citations,
            ...(reasoning?.length ? {reasoning} : {})
        };
    }

    private upsertSummary(conversation: ChatConversation): void {
        const current = this.conversations.filter(item => item.slug !== conversation.slug);
        this.conversations = [{
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
        return error instanceof Error ? error.message : this.dependencies.translator.__('chat.page.loadError');
    }

    private get dependencies(): ChatStoreDependencies {
        if (!this._dependencies) {
            throw new Error('The chat store has not been initialised.');
        }
        return this._dependencies;
    }
}
