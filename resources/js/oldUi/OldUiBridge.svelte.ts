import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import type {ComposerMode, ComposerModeRegistry, ComposerModeWithIs} from '$lib/components/chat/composer/contexts/aspects/ModeAspect.svelte.js';
import type {ChatModeInterface} from '$lib/components/chat/composer/contexts/modes/contracts/ChatModeInterface.js';
import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import type {ResponseBody, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

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
    tools: AiTool[];
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
 * This is a bridge to share state and events between the new Svelte-based UI and the old spaghetti UI.
 */
export class OldUiBridge {
    private sync = new SyncPipeline<SyncFlowList>();
    private async = new AsyncPipeline<ASYNC_PIPELINE_LIST>();
    private isSendingMessage = false;

    /** The user's decrypted passkey for the current session. `null` until the user unlocks. */
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

    public onClearActiveConversation(handler: () => void): () => void {
        return this.sync.on(CLEAR_ACTIVE_CONVERSATION_PIPELINE, handler);
    }

    public triggerClearActiveConversation(): void {
        this.sync.trigger(CLEAR_ACTIVE_CONVERSATION_PIPELINE);
    }

    public triggerLoadInitialModel(model: AiModel): void {
        this.sync.trigger(LOAD_INITIAL_MODEL_PIPELINE, model);
    }

    public onLoadInitialModel(handler: (model: AiModel) => void): () => void {
        return this.sync.on(LOAD_INITIAL_MODEL_PIPELINE, handler);
    }

    public onLoadSystemPrompt(handler: (systemPrompt: string) => void): () => void {
        return this.sync.on(LOAD_SYSTEM_PROMPT_PIPELINE, handler);
    }

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
        return this.sync.on(CONTEXT_READY_PIPELINE, handler);
    }

    public triggerContextReady(): void {
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
        return r.message;
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
