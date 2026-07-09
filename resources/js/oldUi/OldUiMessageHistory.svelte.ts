import type {OldUiConversation, OldUiConversationMessage} from '$lib/oldUi/OldUiBridge.svelte.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

const LOAD_CONVERSATION_EVENT = 'loadConversation';

export class OldUiMessageHistory {
    private sync = new SyncPipeline<{ [LOAD_CONVERSATION_EVENT]: OldUiConversation }>();

    private _type: ComposerContextType = $state('room');
    private _conversation: OldUiConversation | null = null;
    private _systemPrompt = $state('');
    private _conversationName = $state('');
    private _conversationSlug = $state('');
    private _isInConversation = $state(false);

    public readonly conversationName = $derived.by(() => this._conversationName);
    public readonly conversationSlug = $derived.by(() => this._conversationSlug);
    public readonly isInConversation = $derived.by(() => this._isInConversation);
    public readonly systemPrompt = $derived.by(() => this._systemPrompt);
    public readonly canAdministrate = $derived.by(() => this._conversation?.role === 'admin' || this._type === 'aiConv');
    public readonly canWrite = $derived.by(() => this.canAdministrate || this._conversation?.role === 'editor');

    public loadConversation(type: ComposerContextType, conversation: OldUiConversation): void {
        this._type = type;
        this._conversation = {} as any;
        this.updateConversation(conversation);
        this._isInConversation = true;
        this.sync.trigger(LOAD_CONVERSATION_EVENT, conversation);
    }

    public onLoadConversation(handler: (conversation: OldUiConversation) => void): () => void {
        return this.sync.on(LOAD_CONVERSATION_EVENT, handler);
    }

    public updateConversation(update: Partial<OldUiConversation>): void {
        if (!this._conversation) {
            console.warn('No active conversation to update');
            return;
        }
        if (Array.isArray(update.messages)) {
            update = {...update, messages: update.messages.map(m => this.legacyFixMessageContent(m))};
        }
        this._conversation = {...this._conversation, ...update};
        if (update.name !== undefined) {
            this._conversationName = update.name;
        }
        if (update.slug !== undefined) {
            this._conversationSlug = update.slug;
        }
        if (update.system_prompt !== undefined) {
            this._systemPrompt = update.system_prompt;
        }
    }

    public clearConversation(): void {
        this._conversation = null;
        this._conversationName = '';
        this._conversationSlug = '';
        this._systemPrompt = '';
        this._isInConversation = false;
    }

    public addMessageToConversation(message: OldUiConversationMessage): void {
        if (!this._conversation) {
            console.warn('No active conversation to add message to');
            return;
        }
        this._conversation.messages = [...(this._conversation.messages ?? []), this.legacyFixMessageContent(message)];
    }

    public updateMessageInConversation(update: OldUiConversationMessage): void {
        if (!this._conversation) {
            console.warn('No active conversation to update');
            return;
        }
        this._conversation.messages = this._conversation.messages
            .map(m => m.message_id === update.message_id ? this.legacyFixMessageContent(update) : m);
    }

    public removeMessageFromConversation(messageId: string): void {
        if (!this._conversation) {
            console.warn('No active conversation to remove message from');
            return;
        }
        this._conversation.messages = this._conversation.messages
            .filter(m => m.message_id !== messageId);
    }

    public findMessageById(messageId: string): OldUiConversationMessage | null {
        if (!this._conversation) {
            console.warn('No active conversation to find message in');
            return null;
        }
        const message = this._conversation.messages.find(m => m.message_id === messageId);
        if (!message) {
            console.warn(`Message with id ${messageId} not found in conversation`);
            return null;
        }
        return message;
    }

    public findMessageByAttachmentUuid(fileUuid: string): OldUiConversationMessage | null {
        if (!this._conversation) {
            console.warn('No active conversation to find message in');
            return null;
        }
        const message = this._conversation.messages.find(m => m.content?.attachments?.some(a => a.fileData.uuid === fileUuid));
        if (!message) {
            console.warn(`Message with attachment uuid ${fileUuid} not found in conversation`);
            return null;
        }
        return message;
    }

    public removeFileByUuid(fileUuid: string): void {
        if (!this._conversation) {
            console.warn('No active conversation to remove file from');
            return;
        }
        this._conversation.messages = this._conversation.messages
            .map(m => ({
                ...m,
                content: {
                    ...m.content,
                    attachments: m.content?.attachments?.filter(a => a.fileData.uuid !== fileUuid) ?? []
                }
            }));
    }

    private legacyFixMessageContent(message: OldUiConversationMessage): OldUiConversationMessage {
        if (!message.content || typeof message.content.text !== 'string') {
            console.warn('Message content is missing or malformed, applying legacy fix', message);
            return message;
        }

        if (message.content?.text.startsWith('{')) {
            const parsed = JSON.parse(message.content.text);
            return {
                ...message,
                content: {
                    ...message.content,
                    ...parsed
                }
            };
        }

        return message;
    }
}

export const oldUiMessageHistory = new OldUiMessageHistory();
