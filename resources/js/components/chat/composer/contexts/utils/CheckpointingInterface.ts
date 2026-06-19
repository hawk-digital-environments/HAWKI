export interface CheckpointingInterface<T = unknown> {
    createCheckpoint(): T;

    restoreCheckpoint(checkpoint: T): void;
}
