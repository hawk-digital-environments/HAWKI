/**
 * # Composer Context — Architecture Overview
 *
 * `ComposerContext` is the single object all composer components talk to.
 * It aggregates per-domain "slices", a pluggable mode system, and the
 * message-send pipeline. Components access the context via
 * {@link useComposerContext}; a new instance is wired up by
 * {@link createComposerContext} and published into the Svelte context tree.
 *
 * ## Slices
 *
 * State is split into focused slice classes, each owning one concern:
 *
 * | Property                  | Class                 | Owns                                                              |
 * |---------------------------|-----------------------|-------------------------------------------------------------------|
 * | `context.model`           | `ModelSlice`          | selected AI model                                                 |
 * | `context.modelParameters` | `ModelParameterSlice` | temperature / top_p (resets on model switch unless user-modified) |
 * | `context.tools`           | `ToolSlice`           | user-enabled tools for the request                                |
 * | `context.attachments`     | `AttachmentSlice`     | staged file attachments                                           |
 * | `context.modelUsage`      | `ModelUsageSlice`     | derived: is the current model compatible with active tools/files? |
 * | `context.guard`           | `GuardSlice`          | derived: canSend, canChangeMode, disablesFeature()                |
 * | `context.mode`            | `ModeSlice`           | active mode + transition lifecycle                                |
 *
 * `ModelUsageSlice` and `GuardSlice` hold no mutable state — they are
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
 * Every stateful slice implements `CheckpointingInterface`. `ContextCheckpointer`
 * coordinates snapshotting and restoring all of them at once. `ModeSlice` calls
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
 *
 * ## Ownership & lifetime
 *
 * Exactly one context exists per mounted composer. `ChatComposer.svelte`
 * (a `<svelte-snippet>` entry point) calls {@link createComposerContext} in its
 * `<script>` body; every descendant component calls {@link useComposerContext}.
 * The context lives as long as that component subtree — `createComposerContext`
 * registers an `onDestroy` hook that detaches all `OldUiBridge` listeners.
 *
 * ## Reading the state machine (quick map)
 *
 * ```text
 *  user types            -> context.message
 *  guard says ok         -> context.guard.canSend
 *  user hits send        -> context.send()
 *                             -> MessageSender.send(context)
 *                                  -> transport.sendMessage({context, status, ...})
 *                                  -> SendMessageStatus: sending -> responding -> received
 *  mode wants to close   -> context.mode.exit()
 *                             -> ContextCheckpointer.restoreCheckpoint()
 *                                  -> every slice restores its own snapshot
 * ```
 */
import {createContext, onDestroy} from 'svelte';
import {ModelParameterSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelParameterSlice.svelte.js';
import {ModelSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelSlice.svelte.js';
import {AttachmentSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/AttachmentSlice.svelte.js';
import {ToolSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.js';
import {ModelUsageSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelUsageSlice.svelte.js';
import {ContextCheckpointer} from '$plugins/core/modules/chat/components/composer/contexts/utils/ContextCheckpointer.js';
import {ModeSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModeSlice.svelte.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {ChatEditMode} from '$plugins/core/modules/chat/components/composer/contexts/modes/ChatEditMode.js';
import {ChatInThreadMode} from '$plugins/core/modules/chat/components/composer/contexts/modes/ChatInThreadMode.js';
import {ChatRegenMode} from '$plugins/core/modules/chat/components/composer/contexts/modes/ChatRegenMode.js';
import {GuardSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/GuardSlice.svelte.js';
import {MessageSender} from '$plugins/core/modules/chat/components/composer/contexts/sending/MessageSender.js';
import {OldUiBridgeTransport} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/OldUiBridgeTransport.js';
import type {SendMessageStatus} from '$plugins/core/modules/chat/components/composer/contexts/sending/SendMessageStatus.svelte.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte';
import type {MessageSenderTransportInterface} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/MessageSenderTransportInterface.js';

/** The kinds of chat a composer can be mounted into. Validated at runtime by {@link createComposerContext}. */
const allowedContextTypes = ['aiConv', 'room'] as const;

/**
 * Which kind of chat this composer writes into:
 * - `'aiConv'` — a private 1:1 AI conversation. AI controls (model picker, tool
 *   menu, settings) are always visible.
 * - `'room'` — a shared group room. AI controls only appear once the message
 *   addresses the assistant via an `@handle` (see `GuardSlice.showsAiUiElements`).
 */
export type ComposerContextType = typeof allowedContextTypes[number];

export interface CreateComposerContextOptions {
    /** Native routed pages inject their own transport; legacy snippets omit it. */
    transport?: MessageSenderTransportInterface;
    /** Initial prompt for an already loaded conversation. */
    initialSystemPrompt?: string;
    /** Persists prompt edits in the owning routed feature. */
    onSetSystemPrompt?: (prompt: string) => void;
    /** Native implementation of the AI prompt-improvement action. */
    onImproveMessage?: (message: string, systemPrompt: string) => Promise<string>;
    /** Keeps the old DOM bridge subscriptions enabled. Defaults to true. */
    useLegacyBridge?: boolean;
}

/** {@link SyncPipeline} channel name used by {@link ComposerContext.focusInput}. */
const FOCUS_INPUT_PIPELINE = 'focusInput';

/** Channel-to-payload map for the context-internal {@link SyncPipeline}. `void` = no payload. */
interface FlowList {
    [FOCUS_INPUT_PIPELINE]: void;
}

/**
 * Central state container for one composer instance. See the module-level
 * architecture overview for how this relates to slices, modes, and the
 * send pipeline.
 *
 * Obtain the instance for the current component tree via
 * {@link useComposerContext}. Create a new one via {@link createComposerContext}.
 *
 * Never construct this class directly — {@link createComposerContext} performs
 * the (order-sensitive) wiring of all slices, the checkpointer and the sender,
 * and hooks the result up to the legacy UI bridge.
 *
 * @example Consuming it from any descendant component
 * const composerContext = useComposerContext();
 *
 * // reactive reads work because every exposed field is $state / $derived
 * const disabled = $derived(!composerContext.guard.canSend);
 *
 * function onSend() {
 *     const status = composerContext.send();
 *     if (status === null) return; // guard rejected the send
 * }
 */
export class ComposerContext {

    public constructor(
        /** Whether this composer is embedded in a dedicated AI conversation (`'aiConv'`) or a room chat (`'room'`). Affects which AI UI elements are shown. */
        public readonly type: ComposerContextType,
        /** Active mode + its enter/exit lifecycle. See `ModeSlice`. */
        public readonly mode: ModeSlice,
        /** Selected AI model. See `ModelSlice`. */
        public readonly model: ModelSlice,
        /** Sampling parameters (`temperature`, `top_p`) for the next request. See `ModelParameterSlice`. */
        public readonly modelParameters: ModelParameterSlice,
        /** Files staged for the next message. See `AttachmentSlice`. */
        public readonly attachments: AttachmentSlice,
        /** Tools/capabilities enabled for the next message. See `ToolSlice`. */
        public readonly tools: ToolSlice,
        /** Derived compatibility view (model vs. active tools/attachments). See `ModelUsageSlice`. */
        public readonly modelUsage: ModelUsageSlice,
        /** Derived permission view (`canSend`, `canChangeMode`, `disablesFeature`). See `GuardSlice`. */
        public readonly guard: GuardSlice,
        /**
         * Snapshot coordinator used by mode transitions. Private because callers should
         * go through `mode.enter()` / `mode.exit()` (or {@link reset} with `withCheckpoint`)
         * instead of driving checkpoints by hand.
         */
        private readonly checkpointer: ContextCheckpointer,
        /** Send pipeline. Invoked exclusively through {@link send}. */
        private readonly sender: MessageSender,
        /**
         * System prompt this conversation starts with (the `'default'` entry of the
         * `system-prompts` store). {@link reset} returns {@link systemPrompt} to this value.
         */
        private readonly initialSystemPrompt: string,
        /**
         * Called whenever {@link systemPrompt} is assigned, so the legacy UI can persist it.
         * Suppressed while the legacy UI itself is pushing a prompt into the context, to
         * avoid an echo loop (see `blockSystemPromptPropagation` in {@link createComposerContext}).
         */
        private readonly onSetSystemPrompt: (prompt: string) => void,
        /** Improves a draft through the configured AI transport. */
        public readonly improveMessage: (message: string, systemPrompt: string) => Promise<string>,
        /**
         * Extracts agent handles from a text. Backed by the `ai-handle` store; injected
         * so the context stays independent from the store layer.
         * @example getHandlesInText('@hawki hi there') yields '@hawki'
         */
        private readonly getHandlesInText: (text: string) => Generator<string>
    ) {
        this._systemPrompt = $state(initialSystemPrompt);

        // The context is the single participant in the checkpoint protocol: it collects the
        // snapshot of its own fields *plus* one sub-checkpoint per stateful slice, so a mode
        // transition only ever produces/consumes one checkpoint object.
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

    /** Internal event bus for imperative, fire-and-forget signals (currently only "focus the input"). */
    private sync = new SyncPipeline<FlowList>();
    /** Backing field for {@link systemPrompt}; declared as `$state` in the constructor. */
    private _systemPrompt: string;
    /** Backing field for {@link sendStatus}. `null` while idle. */
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
     *  Used by `GuardSlice.canSend` to check whether there is actual content to send. */
    public readonly messageWithoutHandles = $derived.by(() => {
        let text = this.message;
        for (const handle of this.handlesInMessage) {
            text = text.replace(handle, '').trim();
        }
        return text.trim();
    });

    /** All agent `@handle` tokens found in the current message (e.g. `['@hawki']`).
     *  In room mode, the presence of a handle determines whether AI UI elements are shown.
     *  Only one assistant can be addressed at a time (see {@link addHandleToMessage}), so
     *  this normally holds at most one entry — it stays a list because a hand-typed message
     *  can still contain several. */
    public readonly handlesInMessage = $derived.by(() => [...this.getHandlesInText(this.message)]);

    /** `true` when at least one `@hawki` is present in the message. */
    public readonly containsAiHandle = $derived.by(() => this.handlesInMessage.length > 0);

    /** The active send operation, or `null` when the composer is idle. */
    public readonly sendStatus = $derived.by(() => this._sendStatus);

    /** The system prompt for this chat session. Writable — bind or set directly. */
    public get systemPrompt(): string {
        return this._systemPrompt;
    }

    /**
     * Assigning a value also notifies the legacy UI through `onSetSystemPrompt`
     * so it can persist the prompt on the active conversation. Note that
     * {@link reset} deliberately writes the backing field directly and therefore
     * does *not* trigger that notification.
     */
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
    /**
     * Caller contract: this method only *starts* the flow. Reacting to the outcome —
     * surfacing `status.sendIssues` / `status.fileIssues` as toasts, calling {@link clear},
     * and honouring `mode.exitAfterSend` — is the caller's job. `ChatComposer.svelte`
     * (`handleSend`) is the reference implementation of that sequence.
     *
     * @example
     * const status = composerContext.send();
     * if (status === null) return;              // guard rejected it
     * const response = await status.response;   // transport accepted the message
     * if (status.failed) { ... }                // validation / upload problems
     * await response.body;                      // full (possibly streamed) answer received
     */
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
     * Makes `handle` the message's assistant handle, then focuses the input.
     *
     * At most one assistant can be addressed at a time, so this *replaces* any handle
     * already in the message rather than adding to it — tagging a second assistant swaps
     * the first one out.
     */
    public addHandleToMessage(handle: string): void {
        const current = this.handlesInMessage;
        if (current.length !== 1 || current[0] !== handle) {
            this.message = `${handle} ${this.messageWithoutHandles}`;
        }
        this.focusInput();
    }

    /** Removes a single `@handle` token from the message, leaving the rest of the text
     *  untouched, then focuses the input. The counterpart of {@link addHandleToMessage};
     *  use {@link messageWithoutHandles} to strip all of them at once. */
    public removeHandleFromMessage(handle: string): void {
        const handleToken = new RegExp(`(^|\\s)${handle}(?=\\s|$)`, 'g');
        this.message = this.message.replace(handleToken, '$1').trim();
        this.focusInput();
    }

    /**
     * Used after a message has been sent (keeps most of the settings intact, just clears the
     * message and attachments). `sendStatus` is intentionally left untouched — callers invoke
     * this while a send is still in flight (see `ChatComposer.svelte`'s `handleSend`), and
     * `sendStatus` only clears itself once the response body has fully arrived (see {@link send}).
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
    // Note: the model itself is intentionally NOT reset here — only when `withCheckpoint`
    // restores a snapshot does the model revert. Modes such as `ChatRegenMode` rely on this:
    // they call `reset()` first and then set their own model/parameters on the clean slate.
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

/**
 * Svelte context accessor pair for the composer.
 *
 * `createContext()` (Svelte >= 5.40) returns a typed `[get, set]` tuple bound to
 * an internal key, which removes the string/symbol key bookkeeping that plain
 * `getContext`/`setContext` require. `get` is re-exported below as
 * {@link useComposerContext} with an extra guard so a missing provider produces
 * an actionable message instead of Svelte's generic one.
 */
const [get, set] = createContext<ComposerContext>();

/** Returns the `ComposerContext` published by the nearest `createComposerContext` ancestor. */
export function useComposerContext(): ComposerContext {
    const context: ComposerContext | null | undefined = get();
    if (!context) {
        throw new Error('No ComposerContext found in Svelte context tree. Make sure to call createComposerContext() in a parent component.');
    }
    return context;
}

/**
 * Constructs a fully-wired `ComposerContext`, registers it in the Svelte
 * context tree, and subscribes to the relevant `OldUiBridge` events.
 * Call once per composer root component; clean-up is handled automatically
 * via `onDestroy`.
 *
 * ## What it wires (in order — the order matters)
 *
 * 1. Pulls the stores it needs off the app (`ai-models`, `ai-tools`,
 *    `system-prompts`, `ai-handle`).
 * 2. Builds `ModelSlice` and `ModelParameterSlice`. These two reference each
 *    other, so the model slice receives a *factory* (`parameterContextFactory`)
 *    that is only resolved after both objects exist.
 * 3. Builds the `ContextCheckpointer` and the `ModeSlice`. `ModeSlice` gets a
 *    `modeFactory` closure that maps a mode key to a fresh mode instance —
 *    `'default'` is not part of it because `ModeSlice` seeds itself with
 *    `ChatDefaultMode`.
 * 4. Builds the remaining slices. `GuardSlice` and `ModeSlice` receive a
 *    `() => context` resolver for the same circular-dependency reason as above.
 * 5. Builds the `MessageSender` on top of `OldUiBridgeTransport`.
 * 6. Subscribes to the legacy bridge (see the `unbinders` array) and finally
 *    calls `oldUiBridge.triggerContextReady()` to tell the old UI it may start
 *    pushing state (initial model, system prompt, mode changes) into the context.
 *
 * Because the mode/guard slices resolve the context lazily, nothing may call
 * `guard.*` or `mode.*` before this function returns.
 *
 * @param app The running `HawkiApp`; used for stores, config, translator and localization.
 * @param type Whether this composer belongs to an AI conversation or a room. Throws for other values.
 * @param toastContext Toast surface used for mode-transition errors and bridge-forwarded messages.
 *
 * @example Root component (see `plugins/core/snippets/ChatComposer.svelte`)
 * const app = useApp();
 * const toastContext = useToastContext();
 * const chatContext = createComposerContext(app, 'aiConv', toastContext);
 */
export function createComposerContext(
    app: HawkiApp,
    type: ComposerContextType,
    toastContext: ToastContext,
    options: CreateComposerContextOptions = {}
): ComposerContext {
    if (!allowedContextTypes.includes(type)) {
        throw new Error(`Invalid composer context type: ${type}. Allowed types are: ${allowedContextTypes.join(', ')}`);
    }

    // `ModelSlice` needs the parameter slice to decide whether to reset sampling values on a
    // model switch, while `ModelParameterSlice` needs the model slice to read its defaults.
    // The late-bound factory breaks that construction cycle.
    let parameterContext: ModelParameterSlice | null = null;
    const parameterContextFactory = () => parameterContext!;

    const aiModelStore = app.stores.get('ai-models');
    const aiToolStore = app.stores.get('ai-tools');
    const systemPromptStore = app.stores.get('system-prompts');
    const aiHandleStore = app.stores.get('ai-handle');
    const modelSelectionStore = app.stores.get('model-selection');

    // Start on the model the user last picked (persisted per browser) so a
    // rebuilt composer — new chat, conversation switch, reload — keeps the
    // selection instead of falling back to the system default model.
    const rememberedModel = modelSelectionStore.modelId ? aiModelStore.getOneById(modelSelectionStore.modelId) : null;

    const modelContext = new ModelSlice(
        aiModelStore,
        parameterContextFactory,
        (model) => {
            modelSelectionStore.remember(model.model_id);
            if (options.useLegacyBridge !== false) oldUiBridge.updateCurrentChatModelId(model.model_id);
        },
        rememberedModel?.status !== 'offline' ? rememberedModel : null
    );

    parameterContext = new ModelParameterSlice(modelContext);

    const checkpointer = new ContextCheckpointer();
    const mode = new ModeSlice(
        app.translator,
        checkpointer,
        toastContext,
        // modeFactory: maps a mode key to a fresh strategy instance. `'default'` is absent on
        // purpose — `ModeSlice` seeds itself with `ChatDefaultMode` and only ever returns to it
        // by restoring a checkpoint, so asking the factory for it is a programming error.
        (mode) => {
            switch (mode) {
                case 'edit':
                    return new ChatEditMode();
                case 'thread':
                    return new ChatInThreadMode();
                case 'regen':
                    return new ChatRegenMode(aiModelStore, toastContext, app.translator);
                default:
                    throw new Error(`Unsupported mode ${mode}`);
            }
        },
        // Late-bound context resolver: `context` is assigned further down, after all slices exist.
        (): ComposerContext => context,
        // Notifies the legacy UI that a mode was left, handing it the state the mode had
        // *before* the checkpoint was restored (e.g. the edited `messageId`).
        (oldState) => {
            if (options.useLegacyBridge !== false) oldUiBridge.triggerExitMode(oldState);
        }
    );
    const attachment = new AttachmentSlice(app.config);
    const tool = new ToolSlice(modelContext, aiToolStore);
    const guard = new GuardSlice((): ComposerContext => context);
    const modelUsage = new ModelUsageSlice(
        aiModelStore,
        modelContext,
        tool,
        attachment,
        guard
    );

    const initialSystemPrompt = options.initialSystemPrompt ?? systemPromptStore.getPromptByType('default').prompt;

    // Guard against an echo loop: when the legacy UI pushes a prompt in via
    // `onLoadSystemPrompt`, the setter would immediately push the same value back out.
    let blockSystemPromptPropagation = false;
    const onSetSystemPrompt = (prompt: string) => {
        if (blockSystemPromptPropagation) {
            return;
        }
        if (options.onSetSystemPrompt) {
            options.onSetSystemPrompt(prompt);
        } else if (options.useLegacyBridge !== false) {
            oldUiBridge.updateActiveConversationSystemPrompt(prompt);
        }
    };

    const sender = new MessageSender(options.transport ?? new OldUiBridgeTransport(oldUiBridge), app.localization.translator);

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
        options.onImproveMessage ?? ((message, systemPrompt) => oldUiBridge.triggerImproveMessage(message, systemPrompt)),
        (message) => aiHandleStore.getHandlesIn(message)
    );

    const unbinders = options.useLegacyBridge === false ? [] : [
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

    if (options.useLegacyBridge !== false) oldUiBridge.triggerContextReady();
    set(context);

    return context;
}
