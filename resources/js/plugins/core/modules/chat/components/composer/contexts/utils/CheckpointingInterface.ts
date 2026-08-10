/**
 * Snapshot/restore contract implemented by every stateful composer slice.
 *
 * `ContextCheckpointer` calls `createCheckpoint()` on all registered slices
 * when a mode is entered, and `restoreCheckpoint()` on all of them when the
 * mode is exited — so each slice only needs to know about its own state,
 * not about the mode system.
 */
export interface CheckpointingInterface<T = unknown> {
    createCheckpoint(): T;

    restoreCheckpoint(checkpoint: T): void;
}
