/**
 * Snapshot/restore contract implemented by every stateful composer aspect.
 *
 * `ContextCheckpointer` calls `createCheckpoint()` on all registered aspects
 * when a mode is entered, and `restoreCheckpoint()` on all of them when the
 * mode is exited — so each aspect only needs to know about its own state,
 * not about the mode system.
 */
export interface CheckpointingInterface<T = unknown> {
    createCheckpoint(): T;

    restoreCheckpoint(checkpoint: T): void;
}
