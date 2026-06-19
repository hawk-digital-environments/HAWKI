import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {ContextCheckpointer} from '$lib/components/chat/composer/contexts/utils/ContextCheckpointer.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';
import {type OldUiConversationMessage} from '$lib/oldUi/OldUiBridge.svelte.js';
import {ChatDefaultMode} from '$lib/components/chat/composer/contexts/modes/ChatDefaultMode.js';
import type {ChatModeInterface} from '$lib/components/chat/composer/contexts/modes/contracts/ChatModeInterface.js';
import {ChatEditMode} from '$lib/components/chat/composer/contexts/modes/ChatEditMode.js';
import {ChatInThreadMode} from '$lib/components/chat/composer/contexts/modes/ChatInThreadMode.js';
import type {ChatRegenMode} from '$lib/components/chat/composer/contexts/modes/ChatRegenMode.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {__} from '$lib/utils/translator.js';

export interface ComposerModeRegistry {
    default: {
        mode: ChatDefaultMode;
        state: ReturnType<ChatDefaultMode['enter']>;
        data: null;
    };
    edit: {
        mode: ChatEditMode;
        state: ReturnType<ChatEditMode['enter']>;
        data: OldUiConversationMessage
    };
    thread: {
        mode: ChatInThreadMode;
        state: ReturnType<ChatInThreadMode['enter']>;
        data: string;
    };
    regen: {
        mode: ChatRegenMode;
        state: ReturnType<ChatRegenMode['enter']>;
        data: OldUiConversationMessage;
    };
}

type ComposerModeState<T extends keyof ComposerModeRegistry = any> = ComposerModeRegistry[T]['state'];
export type ComposerModeWithIs<T extends keyof ComposerModeRegistry = any> = ComposerModeState<T> & { is: T };
type ComposerModeData<T extends keyof ComposerModeRegistry = any> = ComposerModeRegistry[T]['data'];

export type ComposerMode = keyof ComposerModeRegistry;

interface ModeAspectCheckpoint {
    mode: ChatModeInterface;
    state: ComposerModeWithIs;
}

export type ModeInstanceFactory = (mode: Exclude<ComposerMode, 'default'>) => ChatModeInterface;

export class ModeAspect implements CheckpointingInterface<ModeAspectCheckpoint> {
    constructor(
        private checkpointer: ContextCheckpointer,
        private toast: ToastContext,
        private modeFactory: ModeInstanceFactory,
        private contextResolver: () => ComposerContext,
        private onExitMode: (oldState: ComposerModeWithIs) => void
    ) {
        this._instance = $state(new ChatDefaultMode());
        this._state = $state({is: 'default'});
    }

    private _state: ComposerModeWithIs;
    private _instance: ChatModeInterface;

    public is = $derived.by(() => this._state.is);
    public isDefault = $derived.by(() => this.is === 'default');
    public isEdit = $derived.by(() => this.is === 'edit');
    public isThread = $derived.by(() => this.is === 'thread');
    public isRegen = $derived.by(() => this.is === 'regen');

    public exitAfterSend = $derived.by(() => this._instance.exitAfterSend(this.contextResolver(), this._state));

    public get state(): ComposerModeState {
        return this._state;
    }

    public getState<T extends ComposerMode>(mode: T): ComposerModeWithIs<T> {
        if (this.is !== mode) {
            throw new Error(`Cannot get state for mode ${mode} when current mode is ${this.is}`);
        }
        return this._state;
    }

    public get instance(): ChatModeInterface {
        return this._instance;
    }

    public enter<TMode extends Exclude<ComposerMode, 'default'>>(mode: TMode, data: Extract<ComposerModeRegistry[TMode], { mode: ChatModeInterface }>['data']): void {
        if (this.is === mode) {
            return;
        }

        const context = this.contextResolver();
        if (!context.guard.canChangeMode) {
            this.toast.info(__('chat.composer.modePanel.actionBusy'));
            return;
        }

        const instance = this.modeFactory(mode);

        const enterCheckResult = instance.canEnter(context, data as any);
        if (enterCheckResult !== true) {
            if (enterCheckResult === false) {
                return;
            }
            if (typeof enterCheckResult === 'string') {
                this.toast.error(enterCheckResult);
                return;
            }
        }

        this.checkpointer.createCheckpoint(instance.allowsNestedModes());
        const state = instance.enter(context, data as any);

        this._instance = instance;
        this._state = {...state, is: mode};
    }

    public exit(): void {
        const context = this.contextResolver();
        if (!context.guard.canChangeMode) {
            return;
        }

        this.checkpointer.restoreCheckpoint();
    }

    public createCheckpoint(): ModeAspectCheckpoint {
        return {
            state: {...this._state},
            mode: this._instance
        };
    }

    public restoreCheckpoint(checkpoint: ModeAspectCheckpoint): void {
        const oldState = {...this._state};
        this._instance.exit(this.contextResolver(), this._state);
        this._state = {...checkpoint.state};
        this._instance = checkpoint.mode;
        this.onExitMode(oldState);
    }
}
