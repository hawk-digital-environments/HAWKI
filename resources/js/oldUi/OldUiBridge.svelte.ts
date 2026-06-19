import {applyMigrations, hasPendingMigrations, type MigrationRunType} from '$lib/data/migrations/migrator.js';
import type {ComposerContextType} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import type {ComposerMode, ComposerModeRegistry, ComposerModeWithIs} from '$lib/components/chat/composer/contexts/aspects/ModeAspect.svelte.js';
import type {ChatModeInterface} from '$lib/components/chat/composer/contexts/modes/contracts/ChatModeInterface.js';
import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import type {ResponseBody, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import {ModernEventTarget} from '$lib/utils/ModernEventTarget.js';
import {HookQueue} from '$lib/utils/HookQueue.js';

export interface OldUiConversationMessage {
    author: {
        username: string;
        name: string;
        avatar_url: string;
    },
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
}

export interface OldUiModelParams {
    temp: number;
    top_p: number;
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

const UPDATE_SYSTEM_PROMPT_EVENT = 'updateSystemPrompt';
const UPDATE_CURRENT_CHAT_MODEL_ID_EVENT = 'updateCurrentChatModelId';
const CLEAR_ACTIVE_CONVERSATION_EVENT = 'clearActiveConversation';
const TRIGGER_EXPORT_EVENT = 'triggerExport';
const SEND_MESSAGE_EVENT = 'sendMessage';
const LOAD_INITIAL_MODEL_EVENT = 'loadInitialModel';
const LOAD_SYSTEM_PROMPT_EVENT = 'loadSystemPrompt';
const CONTEXT_READY_EVENT = 'contextReady';
const EXIT_THREAD_EVENT = 'exitThread';
const ENTER_MODE_EVENT = 'enterMode';
const EXIT_MODE_EVENT = 'exitMode';
const OPEN_CHAT_EVENT = 'openChat';
const NEW_CHAT_EVENT = 'newChat';
const RENAME_CHAT_EVENT = 'renameChat';
const DELETE_CHAT_EVENT = 'deleteChat';
const SET_ABORT_CONTROLLER_EVENT = 'abortController';
const IMPROVE_MESSAGE_EVENT = 'improveMessage';
const SEND_TOAST_EVENT = 'sendToast';

interface EventList {
    [UPDATE_SYSTEM_PROMPT_EVENT]: string;
    [UPDATE_CURRENT_CHAT_MODEL_ID_EVENT]: string | null;
    [CLEAR_ACTIVE_CONVERSATION_EVENT]: void;
    [TRIGGER_EXPORT_EVENT]: OldUiExportType;
    [SEND_MESSAGE_EVENT]: OldUiSendMessagePayload;
    [LOAD_INITIAL_MODEL_EVENT]: AiModel;
    [LOAD_SYSTEM_PROMPT_EVENT]: string;
    [CONTEXT_READY_EVENT]: void;
    [EXIT_THREAD_EVENT]: void;
    [ENTER_MODE_EVENT]: { mode: ComposerModeWithIs, data: unknown };
    [EXIT_MODE_EVENT]: ComposerModeWithIs;
    [OPEN_CHAT_EVENT]: string;
    [NEW_CHAT_EVENT]: void;
    [RENAME_CHAT_EVENT]: { chatSlug: string, newName: string };
    [DELETE_CHAT_EVENT]: string;
    [SET_ABORT_CONTROLLER_EVENT]: AbortController;
    [IMPROVE_MESSAGE_EVENT]: { message: string, systemPrompt: string };
    [SEND_TOAST_EVENT]: { message: string, type: 'success' | 'error' | 'info' };
}

const SEND_MESSAGE_HOOK = 'sendMessageHook';
const IMPROVE_MESSAGE_HOOK = 'improveMessageHook';

interface HookList {
    [SEND_MESSAGE_HOOK]: OldUiSendMessagePayload;
    [IMPROVE_MESSAGE_HOOK]: { message: string, systemPrompt: string };
}

/**
 * This is a bridge to share state and events between the new Svelte-based UI and the old spaghetti UI.
 */
export class OldUiBridge {
    private eventTarget = new ModernEventTarget<EventList>();
    private hookQueue = new HookQueue<HookList>();
    private isSendingMessage = false;

    /** The user's decrypted passkey for the current session. `null` until the user unlocks. */
    public passkey = $state<string | null>(null);

    /**
     * Asks the legacy UI to update the system prompt of the active conversation.
     */
    public updateActiveConversationSystemPrompt(newSystemPrompt: string): void {
        this.eventTarget.trigger(UPDATE_SYSTEM_PROMPT_EVENT, newSystemPrompt);
    }

    /** Registers a handler called whenever the legacy UI receives a system-prompt update request. */
    public onActiveConversationSystemPromptUpdate(handler: (newSystemPrompt: string) => void): () => void {
        return this.eventTarget.on(UPDATE_SYSTEM_PROMPT_EVENT, handler);
    }

    public onClearActiveConversation(handler: () => void): () => void {
        return this.eventTarget.on(CLEAR_ACTIVE_CONVERSATION_EVENT, handler);
    }

    public triggerClearActiveConversation(): void {
        this.eventTarget.trigger(CLEAR_ACTIVE_CONVERSATION_EVENT);
    }

    public triggerLoadInitialModel(model: AiModel): void {
        this.eventTarget.trigger(LOAD_INITIAL_MODEL_EVENT, model);
    }

    public onLoadInitialModel(handler: (model: AiModel) => void): () => void {
        return this.eventTarget.on(LOAD_INITIAL_MODEL_EVENT, handler);
    }

    public onLoadSystemPrompt(handler: (systemPrompt: string) => void): () => void {
        return this.eventTarget.on(LOAD_SYSTEM_PROMPT_EVENT, handler);
    }

    public triggerLoadSystemPrompt(systemPrompt: string): void {
        this.eventTarget.trigger(LOAD_SYSTEM_PROMPT_EVENT, systemPrompt);
    }

    /**
     * Requests a model change in the legacy UI.
     */
    public updateCurrentChatModelId(newModelId: string | null): void {
        this.eventTarget.trigger(UPDATE_CURRENT_CHAT_MODEL_ID_EVENT, newModelId);
    }

    /** Registers a handler called whenever the active model ID changes. */
    public onCurrentChatModelIdUpdate(handler: (newModelId: string | null) => void): () => void {
        return this.eventTarget.on(UPDATE_CURRENT_CHAT_MODEL_ID_EVENT, handler);
    }

    /** Asks the legacy UI to export the active conversation in the given format. */
    public triggerExport(exportType: OldUiExportType): void {
        this.eventTarget.trigger(TRIGGER_EXPORT_EVENT, exportType);
    }

    /** Registers a handler called whenever an export is requested. */
    public onExportTrigger(handler: (exportType: OldUiExportType) => void): () => void {
        return this.eventTarget.on(TRIGGER_EXPORT_EVENT, handler);
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
                    const clean = this.eventTarget.on(SET_ABORT_CONTROLLER_EVENT, (ctrl) => {
                        response.setAbortController(ctrl);
                    });
                    response.onDone(() => clean());

                    return handler(response);
                });
            }
        };

        try {
            this.isSendingMessage = true;
            await this.hookQueue.trigger(SEND_MESSAGE_HOOK, extendedPayload);
        } finally {
            this.isSendingMessage = false;
        }
    }

    public onSendMessage(contextType: ComposerContextType, handler: (payload: OldUiSendMessagePayload) => void | Promise<void>): () => void {
        return this.hookQueue.onResultless(SEND_MESSAGE_HOOK, async (payload) => {
            if (payload.contextType === contextType) {
                await handler(payload);
            }
        });
    }

    public bindAbortController(abortController: AbortController): void {
        this.eventTarget.trigger(SET_ABORT_CONTROLLER_EVENT, abortController);
    }

    /**
     * Runs any pending data migrations of the given type.
     * Returns `false` when there are no pending migrations, `true` after they complete.
     */
    public async runMigrations(runType: MigrationRunType): Promise<boolean> {
        if (!hasPendingMigrations()) {
            return false;
        }

        await applyMigrations(runType);
        return true;
    }

    public onContextReady(handler: () => void): () => void {
        return this.eventTarget.on(CONTEXT_READY_EVENT, handler);
    }

    public triggerContextReady(): void {
        this.eventTarget.trigger(CONTEXT_READY_EVENT);
    }

    public onExitThread(handler: () => void): () => void {
        return this.eventTarget.on(EXIT_THREAD_EVENT, handler);
    }

    public triggerExitThread(): void {
        if (this.isSendingMessage) {
            return;
        }
        this.eventTarget.trigger(EXIT_THREAD_EVENT);
    }

    public onEnterMode<TMode extends Exclude<ComposerMode, 'default'>>(handler: (mode: TMode, data: Extract<ComposerModeRegistry[TMode], { mode: ChatModeInterface }>['data']) => void): () => void {
        return this.eventTarget.on(ENTER_MODE_EVENT, ({mode, data}) => {
            handler(mode as TMode, data as any);
        });
    }

    public triggerEnterMode<TMode extends Exclude<ComposerMode, 'default'>>(mode: TMode, data: Extract<ComposerModeRegistry[TMode], { mode: ChatModeInterface }>['data']): void {
        if (this.isSendingMessage) {
            console.warn(`Cannot enter mode ${mode} while sending a message`);
            return;
        }
        this.eventTarget.trigger(ENTER_MODE_EVENT, {mode, data});
    }

    public onExitMode(handler: (mode: ComposerModeWithIs) => void): () => void {
        return this.eventTarget.on(EXIT_MODE_EVENT, handler);
    }

    public triggerExitMode(mode: ComposerModeWithIs): void {
        if (this.isSendingMessage) {
            return;
        }
        this.eventTarget.trigger(EXIT_MODE_EVENT, mode);
    }

    public triggerOpenChat(slug: string): void {
        if (this.isSendingMessage) {
            return;
        }
        this.eventTarget.trigger(OPEN_CHAT_EVENT, slug);
    }

    public onOpenChat(handler: (slug: string) => void): () => void {
        return this.eventTarget.on(OPEN_CHAT_EVENT, handler);
    }

    public triggerNewChat(): void {
        if (this.isSendingMessage) {
            return;
        }
        this.eventTarget.trigger(NEW_CHAT_EVENT);
    }

    public onNewChat(handler: () => void): () => void {
        return this.eventTarget.on(NEW_CHAT_EVENT, handler);
    }

    public triggerRenameChat(chatSlug: string, newName: string): void {
        this.eventTarget.trigger(RENAME_CHAT_EVENT, {chatSlug, newName});
    }

    public onRenameChat(handler: (chatSlug: string, newName: string) => void): () => void {
        return this.eventTarget.on(RENAME_CHAT_EVENT, ({chatSlug, newName}) => {
            handler(chatSlug, newName);
        });
    }

    public triggerDeleteChat(slug: string): void {
        if (this.isSendingMessage) {
            return;
        }
        this.eventTarget.trigger(DELETE_CHAT_EVENT, slug);
    }

    public onDeleteChat(handler: (slug: string) => void): () => void {
        return this.eventTarget.on(DELETE_CHAT_EVENT, handler);
    }

    public triggerImproveMessage(message: string, systemPrompt: string): Promise<string> {
        if (this.isSendingMessage) {
            return Promise.resolve(message);
        }

        return this.hookQueue.trigger(IMPROVE_MESSAGE_HOOK, {message, systemPrompt}).then(r => r.message);
    }

    public onImproveMessage(handler: (data: { message: string, systemPrompt: string }) => string | Promise<string>): () => void {
        return this.hookQueue.on(IMPROVE_MESSAGE_HOOK,
            async (data) => {
                return {
                    ...data,
                    message: await handler(data)
                };
            }
        );
    }

    public triggerSendToast(message: string, type: 'success' | 'error' | 'info'): void {
        this.eventTarget.trigger(SEND_TOAST_EVENT, {message, type});
    }

    public onSendToast(handler: (message: string, type: 'success' | 'error' | 'info') => void): () => void {
        return this.eventTarget.on(SEND_TOAST_EVENT, ({message, type}) => {
            handler(message, type);
        });
    }
}

export const oldUiBridge = new OldUiBridge();
