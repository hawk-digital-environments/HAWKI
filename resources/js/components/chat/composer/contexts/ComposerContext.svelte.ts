/**
 * # Composer Context — Architecture Overview
 *
 * `ComposerContext` is the single object all composer components talk to.
 * It aggregates per-domain "aspects", a pluggable mode system, and the
 * message-send pipeline. Components access the context via
 * {@link useComposerContext}; a new instance is wired up by
 * {@link createComposerContext} and published into the Svelte context tree.
 *
 * ## Aspects
 *
 * State is split into focused aspect classes, each owning one concern:
 *
 * | Property                  | Class                  | Owns                                                              |
 * |---------------------------|------------------------|-------------------------------------------------------------------|
 * | `context.model`           | `ModelAspect`          | selected AI model                                                 |
 * | `context.modelParameters` | `ModelParameterAspect` | temperature / top_p (resets on model switch unless user-modified) |
 * | `context.tools`           | `ToolAspect`           | user-enabled tools for the request                                |
 * | `context.attachments`     | `AttachmentAspect`     | staged file attachments                                           |
 * | `context.modelUsage`      | `ModelUsageAspect`     | derived: is the current model compatible with active tools/files? |
 * | `context.guard`           | `GuardAspect`          | derived: canSend, canChangeMode, disablesFeature()                |
 * | `context.mode`            | `ModeAspect`           | active mode + transition lifecycle                                |
 *
 * `ModelUsageAspect` and `GuardAspect` hold no mutable state — they are
 * pure derived views and are never checkpointed.
 *
 * ## Modes
 *
 * Modes are temporary overlays on the composer. Entering a mode snapshots
 * the current context via `ContextCheckpointer`, the mode instance mutates
 * context for its purpose, and exiting the mode restores the snapshot.
 *
 * | Mode key  | Class              | Purpose                                                 |
 * |-----------|--------------------|---------------------------------------------------------|
 * | `default` | `ChatDefaultMode`  | Normal compose; stays active after send                 |
 * | `edit`    | `ChatEditMode`     | Edit a past user message; locks model/tools/settings UI |
 * | `thread`  | `ChatInThreadMode` | Compose inside a thread; allows nested edit/regen modes |
 * | `regen`   | `ChatRegenMode`    | Regenerate an assistant reply; pre-fills model + params |
 *
 * ## Checkpointing
 *
 * Every stateful aspect implements `CheckpointingInterface`. `ContextCheckpointer`
 * coordinates snapshotting and restoring all of them at once. `ModeAspect` calls
 * the checkpointer on `enter()` / `exit()` so every mode transition is
 * reversible without each mode knowing what to save or restore.
 *
 * ## Sending
 *
 * `MessageSender` orchestrates the send flow. It creates a `SendMessageStatus`
 * (the reactive state machine the UI observes), delegates delivery to a
 * `MessageSenderTransportInterface`, and surfaces a `ResponseReader` through
 * the status once the transport signals a response is arriving.
 * The only concrete transport today is `OldUiBridgeTransport`, which forwards
 * the request to the legacy UI layer.
 */
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
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import {aiHandleStore} from '$lib/stores/AiHandleStore.svelte.js';
import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';

const allowedContextTypes = ['aiConv', 'room'] as const;
export type ComposerContextType = typeof allowedContextTypes[number];

const FOCUS_INPUT_PIPELINE = 'focusInput';

interface FlowList {
    [FOCUS_INPUT_PIPELINE]: void;
}

/**
 * Central state container for one composer instance. See the module-level
 * architecture overview for how this relates to aspects, modes, and the
 * send pipeline.
 *
 * Obtain the instance for the current component tree via
 * {@link useComposerContext}. Create a new one via {@link createComposerContext}.
 */
export class ComposerContext {

    public constructor(
        /** Whether this composer is embedded in a dedicated AI conversation (`'aiConv'`) or a room chat (`'room'`). Affects which AI UI elements are shown. */
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
        private readonly onSetSystemPrompt: (prompt: string) => void,
        private readonly getHandlesInText: (text: string) => Generator<string>
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
            if (this._systemPrompt !== cp.systemPrompt) {
                this.systemPrompt = cp.systemPrompt;
            }
            this.attachments.restoreCheckpoint(cp.attachments);
            // Order of modelParameters and model matters, otherwise the model setting will
            // Reset the parameters to the model defaults.
            this.modelParameters.restoreCheckpoint(cp.parameters);
            this.model.restoreCheckpoint(cp.model);
            this.tools.restoreCheckpoint(cp.tools);
            this.mode.restoreCheckpoint(cp.mode);
        });
    }

    private sync = new SyncPipeline<FlowList>();
    private _systemPrompt: string;
    private _sendStatus = $state(null as SendMessageStatus | null);

    /** Forces the composer into the active/sending state, disabling the send button and other
     *  interactions. Set to `true` when an external process is occupying the composer. */
    public forcedActive = $state(false);

    /** Whether the current conversation allows sending messages. `false` for read-only
     *  conversations (e.g. shared/archived); updated via the `OldUiMessageHistory` bridge. */
    public hasWriteAccess = $state(true);

    /** The user message currently being composed. Writable — bind or set directly. */
    public message = $state('');

    /** The message text with all `@handle` tokens stripped and whitespace normalised.
     *  Used by `GuardAspect.canSend` to check whether there is actual content to send. */
    public readonly messageWithoutHandles = $derived.by(() => {
        let text = this.message;
        for (const handle of this.handlesInMessage) {
            text = text.replace(handle, '').trim();
        }
        return text.trim();
    });

    /** All agent `@handle` tokens found in the current message (e.g. `['@hawki']`).
     *  In room mode, the presence of a handle determines whether AI UI elements are shown. */
    public readonly handlesInMessage = $derived.by(() => [...this.getHandlesInText(this.message)]);

    /** `true` when at least one `@hawki` is present in the message. */
    public readonly containsAiHandle = $derived.by(() => this.handlesInMessage.length > 0);

    /** The active send operation, or `null` when the composer is idle. */
    public readonly sendStatus = $derived.by(() => this._sendStatus);

    /** The system prompt for this chat session. Writable — bind or set directly. */
    public get systemPrompt(): string {
        return this._systemPrompt;
    }

    public set systemPrompt(value: string) {
        this._systemPrompt = value;
        this.onSetSystemPrompt(value);
    }

    /** Imperatively requests that the textarea receives focus. Called by modes after
     *  pre-filling the message so the cursor lands in the input without a user click. */
    public focusInput(): void {
        this.sync.trigger(FOCUS_INPUT_PIPELINE);
    }

    /** Registers a handler that fires whenever {@link focusInput} is called.
     *  Returns an unsubscribe function. Typically called by the textarea component. */
    public onFocusInput(handler: () => void): () => void {
        return this.sync.on(FOCUS_INPUT_PIPELINE, handler);
    }

    /** Starts a send operation. Returns `null` without doing anything when `guard.canSend`
     *  is false. The returned `SendMessageStatus` is also stored on `sendStatus` and cleared
     *  once the response body has fully arrived. */
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

    /** Prepends `handle` to the message if it isn't already present, then focuses the input. */
    public addHandleToMessage(handle: string): void {
        if (!this.handlesInMessage.includes(handle)) {
            this.message = `${handle} ${this.message.trim()}`;
        }
        this.focusInput();
    }

    /**
     * Used after a message has been sent (keeps most of the settings intact, just clears the message, attachments, and sending state).
     * Use {@link reset} to reset everything back to the initial state (e.g. when loading a new conversation or exiting a thread).
     */
    public clear(): void {
        // When the previous message was sent to the ai, we want to keep the handles in the message,
        // so you can keep chatting with the same ai without having to re-tag it in every message.
        const handles = this.handlesInMessage;
        this.message = this.handlesInMessage.join(' ') + (handles.length > 0 ? ' ' : '');
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

/** Returns the `ComposerContext` published by the nearest `createComposerContext` ancestor. */
export function useComposerContext(): ComposerContext {
    return getContext(contextKey);
}

/**
 * Constructs a fully-wired `ComposerContext`, registers it in the Svelte
 * context tree, and subscribes to the relevant `OldUiBridge` events.
 * Call once per composer root component; clean-up is handled automatically
 * via `onDestroy`.
 */
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
        onSetSystemPrompt,
        (message) => aiHandleStore.getHandlesIn(message)
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
        }),
        oldUiMessageHistory.onLoadConversation(() => {
            context.hasWriteAccess = oldUiMessageHistory.canWrite;
        })
    ];

    onDestroy(() => unbinders.forEach(unbind => unbind()));

    oldUiBridge.triggerContextReady();

    setContext(contextKey, context);

    return context;
}
