import type {ComposerContextType} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import type {ComposerMode, ComposerModeRegistry, ComposerModeWithIs} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModeSlice.svelte.js';
import type {ChatModeInterface} from '$plugins/core/modules/chat/components/composer/contexts/modes/contracts/ChatModeInterface.js';
import type {SendMessageStatus} from '$plugins/core/modules/chat/components/composer/contexts/sending/SendMessageStatus.svelte.js';
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import {AiModel} from '$lib/plugins/core/schemas/resources/ai-models.schema';
import {type ResponseBody, SendMessageResponse} from '$plugins/core/modules/chat/components/composer/contexts/sending/SendMessageResponse.svelte.js';
import type {AiToolOrCapabilityWithState} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';

export interface OldUiConversationMessage {
    author: {
        username: string;
        name: string;
        avatar_url: string;
    },
    member_id?: number;
    member_name?: string
    read_status?: boolean;
    completion: number;
    conv_id?: number;
    content: {
        text: string;
        attachments: Array<{
            fileData: {
                uuid: string;
                name: string;
                mime: string;
                type: string;
                url: string;
                category: string;
            }
        }>;
    };
    created_at: string;
    message_id: string;
    message_role: 'user' | 'assistant';
    metadata: {
        tools: null | Record<string, unknown>;
        params: null | Record<string, unknown>;
    },
    model: null | string;
    updated_at: string;
}

export interface OldUiConversation {
    id: number;
    messages: Array<OldUiConversationMessage>;
    name: string;
    slug: string;
    system_prompt: string;
    role?: 'admin' | 'editor' | 'viewer';
}

export interface OldUiModelParams {
    temp: number;
    top_p: number;
}

export interface OldUiFileData {
    category: 'private' | 'group';
    mime: string;
    name: string;
    type: 'image' | 'document';
    url: string;
    uuid: string;
}

export type OldUiExportType = 'print' | 'pdf' | 'word' | 'json' | 'csv';

export interface OldUiSendMessagePayload {
    status: SendMessageStatus;
    setResponse: (body: ResponseBody) => void;
    setResponseFailed: (errorMessage: string) => void;
    waitForResponse: (handler: (tracker: SendMessageResponse) => void | Promise<void>) => void;
    mode: ComposerModeWithIs;
    systemPrompt: string;
    model: AiModel | null;
    contextType: ComposerContextType;
    message: string;
    containsAiHandle: boolean;
    tools: AiToolOrCapabilityWithState[];
    attachments: File[];
    parameters: OldUiModelParams | null;
}

const UPDATE_SYSTEM_PROMPT_PIPELINE = 'updateSystemPrompt';
const UPDATE_CURRENT_CHAT_MODEL_ID_PIPELINE = 'updateCurrentChatModelId';
const CLEAR_ACTIVE_CONVERSATION_PIPELINE = 'clearActiveConversation';
const TRIGGER_EXPORT_PIPELINE = 'triggerExport';
const LOAD_INITIAL_MODEL_PIPELINE = 'loadInitialModel';
const LOAD_SYSTEM_PROMPT_PIPELINE = 'loadSystemPrompt';
const CONTEXT_READY_PIPELINE = 'contextReady';
const EXIT_THREAD_PIPELINE = 'exitThread';
const ENTER_MODE_PIPELINE = 'enterMode';
const EXIT_MODE_PIPELINE = 'exitMode';
const OPEN_CHAT_PIPELINE = 'openChat';
const NEW_CHAT_PIPELINE = 'newChat';
const RENAME_CHAT_PIPELINE = 'renameChat';
const DELETE_CHAT_PIPELINE = 'deleteChat';
const LEAVE_ROOM_PIPELINE = 'leaveRoom';
const SET_ABORT_CONTROLLER_PIPELINE = 'abortController';
const SEND_TOAST_PIPELINE = 'sendToast';
const OPEN_ROOM_CP_PIPELINE = 'openRoomControlPanel';
const MARK_ROOM_MESSAGES_AS_READ_PIPELINE = 'markRoomMessagesAsRead';
const PREVIEW_ATTACHMENT_PIPELINE = 'previewAttachment';
const DOWNLOAD_ATTACHMENT_PIPELINE = 'downloadAttachment';
const DELETE_ATTACHMENT_PIPELINE = 'deleteAttachment';

interface SyncFlowList {
    [UPDATE_SYSTEM_PROMPT_PIPELINE]: string;
    [UPDATE_CURRENT_CHAT_MODEL_ID_PIPELINE]: string | null;
    [CLEAR_ACTIVE_CONVERSATION_PIPELINE]: void;
    [TRIGGER_EXPORT_PIPELINE]: OldUiExportType;
    [LOAD_INITIAL_MODEL_PIPELINE]: AiModel;
    [LOAD_SYSTEM_PROMPT_PIPELINE]: string;
    [CONTEXT_READY_PIPELINE]: void;
    [EXIT_THREAD_PIPELINE]: void;
    [ENTER_MODE_PIPELINE]: { mode: ComposerModeWithIs, data: unknown };
    [EXIT_MODE_PIPELINE]: ComposerModeWithIs;
    [OPEN_CHAT_PIPELINE]: string;
    [NEW_CHAT_PIPELINE]: void;
    [RENAME_CHAT_PIPELINE]: { chatSlug: string, newName: string };
    [DELETE_CHAT_PIPELINE]: string;
    [SET_ABORT_CONTROLLER_PIPELINE]: AbortController;
    [SEND_TOAST_PIPELINE]: { message: string, type: 'success' | 'error' | 'info' };
    [OPEN_ROOM_CP_PIPELINE]: string;
    [MARK_ROOM_MESSAGES_AS_READ_PIPELINE]: string;
    [LEAVE_ROOM_PIPELINE]: string;
}

const SEND_MESSAGE_PIPELINE = 'sendMessage';
const IMPROVE_MESSAGE_PIPELINE = 'improveMessage';

interface ASYNC_PIPELINE_LIST {
    [SEND_MESSAGE_PIPELINE]: OldUiSendMessagePayload;
    [IMPROVE_MESSAGE_PIPELINE]: { message: string, systemPrompt: string, improvedMessage?: string };
    [PREVIEW_ATTACHMENT_PIPELINE]: OldUiFileData;
    [DOWNLOAD_ATTACHMENT_PIPELINE]: OldUiFileData;
    [DELETE_ATTACHMENT_PIPELINE]: OldUiFileData;
}

/**
 * # OldUiBridge — event/state bridge between the old vanilla-JS UI and the new Svelte UI
 *
 * WHAT: a bidirectional messenger. It exposes two internal pipelines — a
 * {@link SyncPipeline} for fire-and-forget events and an {@link AsyncPipeline}
 * for events whose handler(s) must be awaited (e.g. sending a message) — plus
 * one piece of shared reactive state ({@link passkey}). Every public method
 * comes in a `trigger*`/`on*` pair: `trigger*` fires the event, `on*`
 * registers a handler and returns an unsubscribe function.
 *
 * WHY this exists: HAWKI is being migrated from the old plain-JS UI
 * (`public/js/*.js`, e.g. `ai_chat_functions.js`, `groupchat_functions.js`,
 * `chatlog_functions.js`) to the new Svelte 5 app. Both UIs are live on the
 * page at the same time during the migration and need to talk to each other
 * without either side importing the other's module graph (the old UI has no
 * module system — it only sees `window` globals). `OldUiBridge` is the single
 * narrow channel through which that cross-talk happens; it is registered on
 * `window.oldUiBridge` by {@link provideLegacyGlobals} in `legacy.ts`.
 *
 * HOW new Svelte code uses it — call the `trigger*` methods to ask the old
 * UI to do something, and the `on*` methods to react to something the old UI
 * did:
 * ```ts
 * import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
 *
 * // Ask the old UI to open a chat.
 * oldUiBridge.triggerOpenChat(slug);
 *
 * // React to the old UI renaming a chat.
 * const unsubscribe = oldUiBridge.onRenameChat((slug, newName) => { ... });
 * ```
 *
 * HOW the old UI (plain JS, see `public/js/*.js`) uses it — through the
 * global, in the mirror-image direction (it triggers what it owns and
 * listens to what the new UI triggers):
 * ```js
 * window.oldUiBridge.onOpenChat((slug) => { /* old UI switches its view * / });
 * window.oldUiBridge.triggerRenameChat(slug, newName);
 * ```
 *
 * DO NOT use this bridge for new-code-to-new-code communication — it only
 * exists to interoperate with the legacy UI. Once a feature no longer has an
 * old-UI counterpart, its pipeline entry (and the `trigger*`/`on*` pair)
 * should be deleted rather than reused for something else.
 */
export class OldUiBridge {
    /** Fire-and-forget event pipeline; see the class doc for the trigger/on pairing convention. */
    private sync = new SyncPipeline<SyncFlowList>();
    /** Event pipeline whose handlers may be async and are awaited by {@link triggerSendMessage}/{@link triggerImproveMessage}. */
    private async = new AsyncPipeline<ASYNC_PIPELINE_LIST>();
    /** Set for the duration of {@link triggerSendMessage}; several `trigger*` methods no-op while a message is in flight to avoid racing the old UI's send. */
    private isSendingMessage = false;
    /** Flips to `true` the first time {@link triggerContextReady} is called; makes {@link onContextReady} fire immediately for late subscribers. */
    private isContextReady = false;

    /**
     * The user's decrypted passkey for the current session, `null` until the
     * user unlocks. This is `$state`, so new Svelte code can read it
     * reactively (e.g. `$derived(() => oldUiBridge.passkey)`). The **old** UI
     * is the writer — see `public/js/encryption.js`, which sets
     * `window.oldUiBridge.passkey = passKey` once the user unlocks/logs in.
     * New code should only ever read this value, never assign to it.
     */
    public passkey = $state<string | null>(null);

    /**
     * Asks the legacy UI to update the system prompt of the active conversation.
     */
    public updateActiveConversationSystemPrompt(newSystemPrompt: string): void {
        this.sync.trigger(UPDATE_SYSTEM_PROMPT_PIPELINE, newSystemPrompt);
    }

    /** Registers a handler called whenever the legacy UI receives a system-prompt update request. */
    public onActiveConversationSystemPromptUpdate(handler: (newSystemPrompt: string) => void): () => void {
        return this.sync.on(UPDATE_SYSTEM_PROMPT_PIPELINE, handler);
    }

    /** Registers a handler called when the old UI asks the new UI to clear/reset the active conversation view. */
    public onClearActiveConversation(handler: () => void): () => void {
        return this.sync.on(CLEAR_ACTIVE_CONVERSATION_PIPELINE, handler);
    }

    /** Asks the new UI to clear/reset the active conversation view (e.g. after the old UI navigates away). */
    public triggerClearActiveConversation(): void {
        this.sync.trigger(CLEAR_ACTIVE_CONVERSATION_PIPELINE);
    }

    /** Tells the new UI which AI model was active on initial page load, so it can seed the model picker. Called once by the old UI during startup (see `public/js/inputfield_functions.js`). */
    public triggerLoadInitialModel(model: AiModel): void {
        this.sync.trigger(LOAD_INITIAL_MODEL_PIPELINE, model);
    }

    /** Registers a handler called once with the initial AI model reported by the old UI. */
    public onLoadInitialModel(handler: (model: AiModel) => void): () => void {
        return this.sync.on(LOAD_INITIAL_MODEL_PIPELINE, handler);
    }

    /** Registers a handler called whenever the old UI reports the system prompt of the active conversation. */
    public onLoadSystemPrompt(handler: (systemPrompt: string) => void): () => void {
        return this.sync.on(LOAD_SYSTEM_PROMPT_PIPELINE, handler);
    }

    /** Reports the system prompt of the active conversation to the new UI (e.g. after loading or switching a chat). Called by the old UI, see `public/js/ai_chat_functions.js` / `groupchat_functions.js`. */
    public triggerLoadSystemPrompt(systemPrompt: string): void {
        this.sync.trigger(LOAD_SYSTEM_PROMPT_PIPELINE, systemPrompt);
    }

    /**
     * Requests a model change in the legacy UI.
     */
    public updateCurrentChatModelId(newModelId: string | null): void {
        this.sync.trigger(UPDATE_CURRENT_CHAT_MODEL_ID_PIPELINE, newModelId);
    }

    /** Registers a handler called whenever the active model ID changes. */
    public onCurrentChatModelIdUpdate(handler: (newModelId: string | null) => void): () => void {
        return this.sync.on(UPDATE_CURRENT_CHAT_MODEL_ID_PIPELINE, handler);
    }

    /** Asks the legacy UI to export the active conversation in the given format. */
    public triggerExport(exportType: OldUiExportType): void {
        this.sync.trigger(TRIGGER_EXPORT_PIPELINE, exportType);
    }

    /** Registers a handler called whenever an export is requested. */
    public onExportTrigger(handler: (exportType: OldUiExportType) => void): () => void {
        return this.sync.on(TRIGGER_EXPORT_PIPELINE, handler);
    }

    public async triggerSendMessage(payload: OldUiSendMessagePayload): Promise<void> {
        const extendedPayload: OldUiSendMessagePayload = {
            ...payload,
            /**
             * Extends the normal wait for response handler to also bind any provided abort controller to the response,
             * so that calls to `response.abort()` will trigger the abort controller and allow the legacy UI to react to it.
             * @param handler
             */
            waitForResponse: (handler: (tracker: SendMessageResponse) => void | Promise<void>): void => {
                payload.waitForResponse((response) => {
                    const clean = this.sync.on(SET_ABORT_CONTROLLER_PIPELINE, (ctrl) => {
                        response.setAbortController(ctrl);
                    });

                    response.onDone(() => clean());

                    return handler(response);
                });
            }
        };

        try {
            this.isSendingMessage = true;
            await this.async.trigger(SEND_MESSAGE_PIPELINE, extendedPayload);
        } finally {
            this.isSendingMessage = false;
        }
    }

    public onSendMessage(contextType: ComposerContextType, handler: (payload: OldUiSendMessagePayload) => void | Promise<void>): () => void {
        return this.async.on(SEND_MESSAGE_PIPELINE, async (payload) => {
            if (payload.contextType === contextType) {
                await handler(payload);
            }
        });
    }

    public bindAbortController(abortController: AbortController): void {
        this.sync.trigger(SET_ABORT_CONTROLLER_PIPELINE, abortController);
    }

    public onContextReady(handler: () => void): () => void {
        if (this.isContextReady) {
            handler();
        }
        return this.sync.on(CONTEXT_READY_PIPELINE, handler);
    }

    public triggerContextReady(): void {
        this.isContextReady = true;
        this.sync.trigger(CONTEXT_READY_PIPELINE);
    }

    public onExitThread(handler: () => void): () => void {
        return this.sync.on(EXIT_THREAD_PIPELINE, handler);
    }

    public triggerExitThread(): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(EXIT_THREAD_PIPELINE);
    }

    public onEnterMode<TMode extends Exclude<ComposerMode, 'default'>>(handler: (mode: TMode, data: Extract<ComposerModeRegistry[TMode], { mode: ChatModeInterface }>['data']) => void): () => void {
        return this.sync.on(ENTER_MODE_PIPELINE, ({mode, data}) => {
            handler(mode as TMode, data as any);
        });
    }

    public triggerEnterMode<TMode extends Exclude<ComposerMode, 'default'>>(mode: TMode, data: Extract<ComposerModeRegistry[TMode], { mode: ChatModeInterface }>['data']): void {
        if (this.isSendingMessage) {
            console.warn(`Cannot enter mode ${mode} while sending a message`);
            return;
        }
        this.sync.trigger(ENTER_MODE_PIPELINE, {mode, data});
    }

    public onExitMode(handler: (mode: ComposerModeWithIs) => void): () => void {
        return this.sync.on(EXIT_MODE_PIPELINE, handler);
    }

    public triggerExitMode(mode: ComposerModeWithIs): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(EXIT_MODE_PIPELINE, mode);
    }

    public triggerOpenChat(slug: string): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(OPEN_CHAT_PIPELINE, slug);
    }

    public onOpenChat(handler: (slug: string) => void): () => void {
        return this.sync.on(OPEN_CHAT_PIPELINE, handler);
    }

    public triggerNewChat(): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(NEW_CHAT_PIPELINE);
    }

    public onNewChat(handler: () => void): () => void {
        return this.sync.on(NEW_CHAT_PIPELINE, handler);
    }

    public triggerRenameChat(chatSlug: string, newName: string): void {
        this.sync.trigger(RENAME_CHAT_PIPELINE, {chatSlug, newName});
    }

    public onRenameChat(handler: (chatSlug: string, newName: string) => void): () => void {
        return this.sync.on(RENAME_CHAT_PIPELINE, ({chatSlug, newName}) => {
            handler(chatSlug, newName);
        });
    }

    public triggerDeleteChat(slug: string): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(DELETE_CHAT_PIPELINE, slug);
    }

    public onDeleteChat(handler: (slug: string) => void): () => void {
        return this.sync.on(DELETE_CHAT_PIPELINE, handler);
    }

    public triggerLeaveRoom(slug: string): void {
        if (this.isSendingMessage) {
            return;
        }
        this.sync.trigger(LEAVE_ROOM_PIPELINE, slug);
    }

    public onLeaveRoom(handler: (slug: string) => void): () => void {
        return this.sync.on(LEAVE_ROOM_PIPELINE, handler);
    }

    public async triggerImproveMessage(message: string, systemPrompt: string): Promise<string> {
        if (this.isSendingMessage) {
            return Promise.resolve(message);
        }

        const r = await this.async.trigger(IMPROVE_MESSAGE_PIPELINE, {message, systemPrompt});
        return r.improvedMessage ?? message;
    }

    public onImproveMessage(handler: (data: { message: string, systemPrompt: string }) => string | Promise<string>): () => void {
        return this.async.on(IMPROVE_MESSAGE_PIPELINE, async (data) => {
            data.improvedMessage = await handler(data);
        });
    }

    public triggerSendToast(message: string, type: 'success' | 'error' | 'info'): void {
        this.sync.trigger(SEND_TOAST_PIPELINE, {message, type});
    }

    public onSendToast(handler: (message: string, type: 'success' | 'error' | 'info') => void): () => void {
        return this.sync.on(SEND_TOAST_PIPELINE, ({message, type}) => handler(message, type));
    }

    public triggerOpenRoomControlPanel(slug: string): void {
        this.sync.trigger(OPEN_ROOM_CP_PIPELINE, slug);
    }

    public onOpenRoomControlPanel(handler: (slug: string) => void): () => void {
        return this.sync.on(OPEN_ROOM_CP_PIPELINE, handler);
    }

    public triggerMarkRoomMessagesAsRead(slug: string): void {
        this.sync.trigger(MARK_ROOM_MESSAGES_AS_READ_PIPELINE, slug);
    }

    public onMarkRoomMessagesAsRead(handler: (slug: string) => void): () => void {
        return this.sync.on(MARK_ROOM_MESSAGES_AS_READ_PIPELINE, handler);
    }

    public triggerPreviewAttachment(fileData: OldUiFileData): void {
        this.async.trigger(PREVIEW_ATTACHMENT_PIPELINE, fileData);
    }

    public onPreviewAttachment(handler: (fileData: OldUiFileData) => void | Promise<void>): () => void {
        return this.async.on(PREVIEW_ATTACHMENT_PIPELINE, handler);
    }

    public triggerDownloadAttachment(fileData: OldUiFileData): void {
        this.async.trigger(DOWNLOAD_ATTACHMENT_PIPELINE, fileData);
    }

    public onDownloadAttachment(handler: (fileData: OldUiFileData) => void | Promise<void>): () => void {
        return this.async.on(DOWNLOAD_ATTACHMENT_PIPELINE, handler);
    }

    public triggerDeleteAttachment(fileData: OldUiFileData): void {
        this.async.trigger(DELETE_ATTACHMENT_PIPELINE, fileData);
    }

    public onDeleteAttachment(handler: (fileData: OldUiFileData) => void | Promise<void>): () => void {
        return this.async.on(DELETE_ATTACHMENT_PIPELINE, handler);
    }
}

export const oldUiBridge = new OldUiBridge();
