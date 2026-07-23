import {AttachmentAspect} from '$lib/components/chat/composer/contexts/aspects/AttachmentAspect.svelte.js';
import {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import {ModelParameterAspect} from '$lib/components/chat/composer/contexts/aspects/ModelParameterAspect.svelte.js';
import type {ModeAspect} from '$lib/components/chat/composer/contexts/aspects/ModeAspect.svelte.js';
import type {ToolAspect} from '$lib/components/chat/composer/contexts/aspects/ToolAspect.svelte.js';
import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

const CREATE_CHECKPOINT_PIPELINE = 'createCheckpoint';
const RESTORE_CHECKPOINT_PIPELINE = 'restoreCheckpoint';

interface FlowList {
    [CREATE_CHECKPOINT_PIPELINE]: boolean;
    [RESTORE_CHECKPOINT_PIPELINE]: void;
}

interface ComposerContextCheckpoint {
    status: SendMessageStatus | null;
    message: string;
    systemPrompt: string;
    tools: ReturnType<ToolAspect['createCheckpoint']>;
    attachments: ReturnType<AttachmentAspect['createCheckpoint']>;
    model: ReturnType<ModelAspect['createCheckpoint']>;
    parameters: ReturnType<ModelParameterAspect['createCheckpoint']>;
    mode: ReturnType<ModeAspect['createCheckpoint']>;
}

interface ComposerContextCheckpointWithMeta {
    allowsNested: boolean;
    checkpoint: ComposerContextCheckpoint;
}

/**
 * Coordinates snapshot and restore of the entire composer state across all
 * aspects at once.
 *
 * Each aspect (and `ComposerContext` itself) registers its own
 * `onCreateCheckpoint` / `onRestoreCheckpoint` handler. When `ModeAspect`
 * calls {@link createCheckpoint} on entering a mode, every registered handler
 * fires and pushes its slice of state into a single checkpoint object.
 * {@link restoreCheckpoint} pops the stack and broadcasts the saved slices
 * back to all handlers.
 *
 * The `allowsNested` flag controls whether a second `createCheckpoint` call
 * is allowed while one is already active. `ChatInThreadMode` passes `true`
 * so that edit/regen modes can be layered on top of an active thread without
 * losing the thread's checkpoint.
 */
export class ContextCheckpointer {
    private _checkpoints: ComposerContextCheckpointWithMeta[] = [];
    private sync = new SyncPipeline<FlowList>();

    public get hasCheckpoint(): boolean {
        return this._checkpoints.length > 0;
    }

    public get allowsNestedCheckpoints(): boolean {
        if (!this.hasCheckpoint) {
            return false;
        }
        return this._checkpoints[this._checkpoints.length - 1].allowsNested;
    }

    /**
     * Triggers a snapshot of the full composer state. No-op when a checkpoint
     * already exists and it does not allow nesting.
     */
    public createCheckpoint(allowsNested?: boolean): void {
        if (this.hasCheckpoint && !this.allowsNestedCheckpoints) {
            return;
        }
        this.sync.trigger(CREATE_CHECKPOINT_PIPELINE, allowsNested ?? false);
    }

    /** Restores the most recent snapshot and removes it from the stack. */
    public restoreCheckpoint(): void {
        if (!this.hasCheckpoint) {
            return;
        }
        this.sync.trigger(RESTORE_CHECKPOINT_PIPELINE);
        this._checkpoints.pop();
    }

    /**
     * Registers a handler that participates in checkpoint creation.
     * The handler receives a `check` callback — call it with the snapshot
     * value to push it onto the checkpoint stack.
     * Returns an unsubscribe function.
     */
    public onCreateCheckpoint(listener: (check: ((value: ComposerContextCheckpoint) => void)) => void): () => void {
        return this.sync.on(CREATE_CHECKPOINT_PIPELINE, (allowsNested) => {
            listener((checkpoint: ComposerContextCheckpoint) => {
                this._checkpoints.push({allowsNested, checkpoint});
            });
        });
    }

    /**
     * Registers a handler that participates in checkpoint restoration.
     * The handler receives the most recent snapshot value.
     * Returns an unsubscribe function.
     */
    public onRestoreCheckpoint(listener: (value: ComposerContextCheckpoint) => void): () => void {
        return this.sync.on(RESTORE_CHECKPOINT_PIPELINE, () => {
            const checkpoint = this._checkpoints[this._checkpoints.length - 1];
            if (checkpoint) {
                listener(checkpoint.checkpoint);
            }
        });
    }
}
