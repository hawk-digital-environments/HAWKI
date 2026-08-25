import type {OldUiConversation, OldUiConversationMessage} from '$lib/legacy/OldUiBridge.svelte.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import type {ComposerContextType} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

/** Pipeline key for the "a conversation was (re)loaded" event, see {@link OldUiMessageHistory.onLoadConversation}. */
const LOAD_CONVERSATION_EVENT = 'loadConversation';

/**
 * # OldUiMessageHistory — shared, reactive mirror of the conversation the old UI has open
 *
 * **Part of the transitional `legacy/` bridge.** It exists only while HAWKI
 * still runs its old Blade + vanilla-JS chat UI (`public/js/ai_chat_functions.js`,
 * `groupchat_functions.js`, `chatlog_functions.js`, ...) next to the new Svelte
 * 5 app, and is meant to be deleted once the SPA owns the conversation state.
 *
 * WHAT: a single mutable store holding the currently-open conversation (its
 * name, slug, system prompt, role and full message list) plus a handful of
 * `$derived` read-only views onto it.
 *
 * WHY it exists: the **old UI is the owner** of chat state — it loads
 * conversations, streams messages and renders the message list. New Svelte
 * pieces embedded into that page (the chat header, the composer, the
 * attachment dropdown, …) still need to read that state reactively. Because
 * the two UIs share no module graph, the old JS writes into this store through
 * `window.oldUiMessageHistory` (published by {@link provideLegacyGlobals}) and
 * new code reads it as ordinary Svelte 5 runes state.
 *
 * Division of labour with {@link OldUiBridge}: the bridge carries **events**
 * (do this / this happened), this class carries **data** (what is currently on
 * screen).
 *
 * HOW new Svelte code uses it — read-only, reactively:
 * ```svelte
 * <script lang="ts">
 *     import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
 *     const name = $derived(oldUiMessageHistory.conversationName);
 * </script>
 * {#if oldUiMessageHistory.isInConversation}<h1>{name}</h1>{/if}
 * ```
 *
 * HOW the old UI uses it — as the writer:
 * ```js
 * window.oldUiMessageHistory.loadConversation('room', activeRoom);
 * window.oldUiMessageHistory.addMessageToConversation(messageData);
 * window.oldUiMessageHistory.clearConversation();
 * ```
 *
 * DO NOT build new features on this class. It mirrors the legacy payload shapes
 * ({@link OldUiConversation}) one-to-one, including their quirks — see
 * {@link OldUiMessageHistory.legacyFixMessageContent}.
 */
export class OldUiMessageHistory {
    /** Fire-and-forget pipeline used for {@link onLoadConversation}. */
    private sync = new SyncPipeline<{ [LOAD_CONVERSATION_EVENT]: OldUiConversation }>();

    /** Which kind of conversation is open: a group `room` or a private AI conversation (`aiConv`). Drives {@link canAdministrate}. */
    private _type: ComposerContextType = $state('room');
    /** The open conversation, or `null` when none is (see {@link clearConversation}). */
    private _conversation = $state<OldUiConversation | null>(null);
    /** Mirrored out of `_conversation.system_prompt` so it survives partial updates that omit the field. */
    private _systemPrompt = $state('');
    /** Whether {@link loadConversation} has run without a following {@link clearConversation}. */
    private _isInConversation = $state(false);

    /** Display name of the open conversation; empty string when none is open. */
    public readonly conversationName = $derived.by(() => this._conversation?.name ?? '');
    /** URL slug of the open conversation; empty string when none is open. Also used by the old UI to detect chat switches. */
    public readonly conversationSlug = $derived.by(() => this._conversation?.slug ?? '');
    /** `true` while a conversation is open. Use this to gate UI that would otherwise render an empty header/composer. */
    public readonly isInConversation = $derived.by(() => this._isInConversation);
    /** The conversation currently mirrored from the old UI. */
    public readonly conversation = $derived.by(() => this._conversation);
    /** System prompt of the open conversation; empty string when none is open. */
    public readonly systemPrompt = $derived.by(() => this._systemPrompt);
    /**
     * Whether the current user may administrate the conversation (rename, delete,
     * open the room control panel, change the system prompt).
     *
     * Always `true` for private AI conversations (`aiConv`), since the user owns
     * them; for rooms it requires the `admin` member role.
     */
    public readonly canAdministrate = $derived.by(() => this._conversation?.role === 'admin' || this._type === 'aiConv');
    /** Whether the current user may post messages: admins plus room `editor`s. Read by the composer to disable input for viewers. */
    public readonly canWrite = $derived.by(() => this.canAdministrate || this._conversation?.role === 'editor');

    /**
     * Replaces the whole store with a freshly-opened conversation and notifies
     * {@link onLoadConversation} subscribers. Called by the old UI whenever it
     * opens or switches a chat.
     *
     * @param type `'room'` for group chats, `'aiConv'` for private AI conversations.
     * @param conversation The full conversation payload as the legacy backend returns it.
     */
    public loadConversation(type: ComposerContextType, conversation: OldUiConversation): void {
        this._type = type;
        this._conversation = {} as any;
        this.updateConversation(conversation);
        this._isInConversation = true;
        this.sync.trigger(LOAD_CONVERSATION_EVENT, conversation);
    }

    /**
     * Registers a handler that fires every time a conversation is opened or
     * switched. Handlers are **not** replayed for a conversation that was
     * already loaded before subscribing.
     *
     * @returns an unsubscribe function.
     */
    public onLoadConversation(handler: (conversation: OldUiConversation) => void): () => void {
        return this.sync.on(LOAD_CONVERSATION_EVENT, handler);
    }

    /**
     * Shallow-merges a partial update into the open conversation — used for
     * renames, system-prompt changes and full message-list refreshes.
     *
     * Passing `messages` replaces the whole list (each entry goes through
     * {@link legacyFixMessageContent}); to touch a single message use
     * {@link addMessageToConversation} / {@link updateMessageInConversation}.
     *
     * No-ops with a console warning when no conversation is open.
     */
    public updateConversation(update: Partial<OldUiConversation>): void {
        if (!this._conversation) {
            console.warn('No active conversation to update');
            return;
        }
        if (Array.isArray(update.messages)) {
            update = {...update, messages: update.messages.map(m => this.legacyFixMessageContent(m))};
        }
        this._conversation = {...this._conversation, ...update};
        if (update.system_prompt !== undefined) {
            this._systemPrompt = update.system_prompt;
        }
    }

    /**
     * Resets the store to "no conversation open". Called by the old UI when the
     * user navigates away from a chat or starts a new one.
     */
    public clearConversation(): void {
        this._conversation = null;
        this._systemPrompt = '';
        this._isInConversation = false;
    }

    /**
     * Appends a message to the open conversation (new user message, new AI
     * answer, new room message received over the websocket).
     *
     * No-ops with a console warning when no conversation is open.
     */
    public addMessageToConversation(message: OldUiConversationMessage): void {
        if (!this._conversation) {
            console.warn('No active conversation to add message to');
            return;
        }
        this._conversation.messages = [...(this._conversation.messages ?? []), this.legacyFixMessageContent(message)];
    }

    /**
     * Replaces the message with the same `message_id` by `update` (used for
     * streaming updates, edits and read-status changes). Silently does nothing
     * when no message with that id exists.
     *
     * No-ops with a console warning when no conversation is open.
     */
    public updateMessageInConversation(update: OldUiConversationMessage): void {
        if (!this._conversation) {
            console.warn('No active conversation to update');
            return;
        }
        this._conversation.messages = this._conversation.messages
            .map(m => m.message_id === update.message_id ? this.legacyFixMessageContent(update) : m);
    }

    /**
     * Drops the message with the given `message_id` from the open conversation.
     *
     * No-ops with a console warning when no conversation is open.
     */
    public removeMessageFromConversation(messageId: string): void {
        if (!this._conversation) {
            console.warn('No active conversation to remove message from');
            return;
        }
        this._conversation.messages = this._conversation.messages
            .filter(m => m.message_id !== messageId);
    }

    /**
     * Looks up a message by its `message_id`.
     *
     * @returns the message, or `null` when no conversation is open or the id is
     *          unknown. Both cases log a warning, because in practice they mean
     *          the caller's id came from stale DOM.
     */
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

    /**
     * Finds the message that carries the attachment with the given file uuid.
     * Used by the attachment dropdown, which only knows the file — not the
     * message it hangs off — to resolve permissions/context.
     *
     * @returns the owning message, or `null` (with a warning) when no
     *          conversation is open or no message carries that attachment.
     */
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

    /**
     * Removes an attachment from *every* message of the open conversation,
     * after the file itself was deleted. Sweeping all messages (rather than
     * just the owning one) keeps the store consistent even if the same file was
     * referenced more than once.
     *
     * No-ops with a console warning when no conversation is open.
     */
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

    /**
     * Legacy quirk: some backend paths serialize extra fields (e.g. attachments)
     * into `content.text` as a JSON string instead of populating `content`
     * directly. Detects that shape (`text` starting with `{`) and merges the
     * parsed object into `content`, leaving normal plain-text messages untouched.
     */
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
