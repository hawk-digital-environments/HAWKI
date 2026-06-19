import {aiModelStore} from '$lib/stores/AiModelStore.svelte.js';
import {aiToolStore} from '$lib/stores/AiToolStore.svelte.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
import {getContext, onDestroy, setContext} from 'svelte';
import {systemPromptStore} from '$lib/stores/SystemPromptStore.svelte.js';
import {ModelParameterAspect} from '$lib/components/chat/composer/contexts/aspects/ModelParameterAspect.svelte.js';
import {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import {AttachmentAspect} from '$lib/components/chat/composer/contexts/aspects/AttachmentAspect.svelte.js';
import {ToolAspect} from '$lib/components/chat/composer/contexts/aspects/ToolAspect.svelte.js';
import {ModelUsageAspect} from '$lib/components/chat/composer/contexts/aspects/ModelUsageAspect.svelte.js';
import {ContextCheckpointer} from '$lib/components/chat/composer/contexts/utils/ContextCheckpointer.js';
import {ModeAspect} from '$lib/components/chat/composer/contexts/aspects/ModeAspect.svelte.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {ChatEditMode} from '$lib/components/chat/composer/contexts/modes/ChatEditMode.js';
import {ChatInThreadMode} from '$lib/components/chat/composer/contexts/modes/ChatInThreadMode.js';
import {ChatRegenMode} from '$lib/components/chat/composer/contexts/modes/ChatRegenMode.js';
import {GuardAspect} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';
import {MessageSender} from '$lib/components/chat/composer/contexts/sending/MessageSender.js';
import {OldUiBridgeTransport} from '$lib/components/chat/composer/contexts/sending/transport/OldUiBridgeTransport.js';
import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {ModernEventTarget} from '$lib/utils/ModernEventTarget.js';

const allowedContextTypes = ['aiConv', 'room'] as const;
export type ComposerContextType = typeof allowedContextTypes[number];

const FOCUS_INPUT_EVENT = 'focusInput';

export class ComposerContext {

    public constructor(
        public readonly type: ComposerContextType,
        public readonly mode: ModeAspect,
        public readonly model: ModelAspect,
        public readonly modelParameters: ModelParameterAspect,
        public readonly attachments: AttachmentAspect,
        public readonly tools: ToolAspect,
        public readonly modelUsage: ModelUsageAspect,
        public readonly guard: GuardAspect,
        private readonly checkpointer: ContextCheckpointer,
        private readonly sender: MessageSender,
        private readonly initialSystemPrompt: string,
        private readonly onSetSystemPrompt: (prompt: string) => void
    ) {
        this._systemPrompt = $state(initialSystemPrompt);

        this.checkpointer.onCreateCheckpoint((check) => {
            check({
                status: this._sendStatus,
                message: this.message,
                systemPrompt: this.systemPrompt,
                tools: this.tools.createCheckpoint(),
                attachments: this.attachments.createCheckpoint(),
                model: this.model.createCheckpoint(),
                parameters: this.modelParameters.createCheckpoint(),
                mode: this.mode.createCheckpoint()
            });
        });

        this.checkpointer.onRestoreCheckpoint((cp) => {
            this._sendStatus = cp.status;
            this.message = cp.message;
            this.systemPrompt = cp.systemPrompt;
            this.attachments.restoreCheckpoint(cp.attachments);
            // Order of modelParameters and model matters, otherwise the model setting will
            // Reset the parameters to the model defaults.
            this.modelParameters.restoreCheckpoint(cp.parameters);
            this.model.restoreCheckpoint(cp.model);
            this.tools.restoreCheckpoint(cp.tools);
            this.mode.restoreCheckpoint(cp.mode);
        });
    }

    private eventTarget = new ModernEventTarget();
    private _systemPrompt: string;
    private _sendStatus = $state(null as SendMessageStatus | null);

    /** Can be set to true, to programmatically force the composer into the active/sending state,
     * which disables the send button and other interactions. */
    public forcedActive = $state(false);

    /** The user message currently being composed. Writable — bind or set directly. */
    public message = $state('');

    public readonly sendStatus = $derived.by(() => this._sendStatus);

    /** The system prompt for this chat session. Writable — bind or set directly. */
    public get systemPrompt(): string {
        return this._systemPrompt;
    }

    public set systemPrompt(value: string) {
        this._systemPrompt = value;
        this.onSetSystemPrompt(value);
    }

    public focusInput(): void {
        this.eventTarget.trigger(FOCUS_INPUT_EVENT);
    }

    public onFocusInput(handler: () => void): () => void {
        return this.eventTarget.on(FOCUS_INPUT_EVENT, handler);
    }

    public send(): SendMessageStatus | null {
        if (!this.guard.canSend) {
            return null;
        }

        const status = this.sender.send(this);

        this._sendStatus = status;

        status.response.then((res) => {
            res.body.then(() => {
                // Clear the send status only after the response body has been fully received
                this._sendStatus = null;
            });
        });

        return status;
    }

    /**
     * Used after a message has been sent (keeps most of the settings intact, just clears the message, attachments, and sending state).
     * Use {@link reset} to reset everything back to the initial state (e.g. when loading a new conversation or exiting a thread).
     */
    public clear(): void {
        this.message = '';
        this.attachments.clear();
    }

    /**
     * Resets the entire context back to the initial state. If `withCheckpoint` is true,
     * it will also restore the context to the last original checkpoint (exiting modes that are active).
     * To just clear the input and attachments after sending a message, use {@link clear} instead.
     * @param withCheckpoint
     */
    public reset(withCheckpoint?: boolean): void {
        if (withCheckpoint) {
            this.checkpointer.restoreCheckpoint();
        }
        this.message = '';
        this.attachments.clear();
        this.modelParameters.reset();
        this.tools.clear();
        this._systemPrompt = this.initialSystemPrompt;
        this._sendStatus = null;
    }
}

const contextKey = Symbol('chatComposer');

export function useComposerContext(): ComposerContext {
    return getContext(contextKey);
}

export function createComposerContext(
    type: ComposerContextType,
    toastContext: ToastContext
): ComposerContext {
    if (!allowedContextTypes.includes(type)) {
        throw new Error(`Invalid composer context type: ${type}. Allowed types are: ${allowedContextTypes.join(', ')}`);
    }

    let parameterContext: ModelParameterAspect | null = null;
    const parameterContextFactory = () => parameterContext!;

    const modelContext = new ModelAspect(
        aiModelStore,
        parameterContextFactory,
        (model) => oldUiBridge.updateCurrentChatModelId(model.model_id)
    );

    parameterContext = new ModelParameterAspect(modelContext);

    const checkpointer = new ContextCheckpointer();
    const mode = new ModeAspect(
        checkpointer,
        toastContext,
        (mode) => {
            switch (mode) {
                case 'edit':
                    return new ChatEditMode();
                case 'thread':
                    return new ChatInThreadMode();
                case 'regen':
                    return new ChatRegenMode(aiModelStore, aiToolStore, toastContext);
                default:
                    throw new Error(`Unsupported mode ${mode}`);
            }
        },
        (): ComposerContext => context,
        (oldState) => oldUiBridge.triggerExitMode(oldState)
    );
    const attachment = new AttachmentAspect();
    const tool = new ToolAspect(modelContext, aiToolStore);
    const guard = new GuardAspect((): ComposerContext => context);
    const modelUsage = new ModelUsageAspect(
        aiModelStore,
        aiToolStore,
        modelContext,
        tool,
        attachment,
        guard
    );

    const initialSystemPrompt = systemPromptStore.getPromptByType('default').prompt ?? '';

    let blockSystemPromptPropagation = false;
    const onSetSystemPrompt = (prompt: string) => {
        if (blockSystemPromptPropagation) {
            return;
        }
        oldUiBridge.updateActiveConversationSystemPrompt(prompt);
    };

    const sender = new MessageSender(new OldUiBridgeTransport(oldUiBridge));

    const context = new ComposerContext(
        type,
        mode,
        modelContext,
        parameterContext,
        attachment,
        tool,
        modelUsage,
        guard,
        checkpointer,
        sender,
        initialSystemPrompt,
        onSetSystemPrompt
    );

    const unbinders = [
        oldUiBridge.onClearActiveConversation(() => {
            context.reset(true);
        }),
        oldUiBridge.onLoadSystemPrompt(prompt => {
            try {
                blockSystemPromptPropagation = true;
                context.systemPrompt = prompt;
            } finally {
                blockSystemPromptPropagation = false;
            }
        }),
        oldUiBridge.onLoadInitialModel(model => {
            context.model.set(model);
        }),
        oldUiBridge.onEnterMode((mode, data) => {
            context.mode.enter(mode, data);
        }),
        oldUiBridge.onExitThread(() => {
            if (context.mode.isThread) {
                context.mode.exit();
            }
        }),
        oldUiBridge.onSendToast((message, type) => {
            if (type === 'success') {
                toastContext.success(message);
            } else if (type === 'error') {
                toastContext.error(message);
            } else {
                toastContext.info(message);
            }
        })
    ];

    onDestroy(() => unbinders.forEach(unbind => unbind()));

    oldUiBridge.triggerContextReady();

    setContext(contextKey, context);

    return context;
}
