/**
 * Dynamic query timing: debounce, app-wide concurrency, deadlines and
 * cancellation.
 *
 * Two objects live here. {@link ConcurrencyGate} is shared by every session, so
 * four bars typing at once still make four server requests at a time rather
 * than four each. {@link DynamicQueryRunner} belongs to one session and owns a
 * *generation*: every restart invalidates the replies of the previous one, so a
 * transport that fails to abort — or a provider that ignores its signal — can
 * never resurrect results for a query the user has moved on from.
 *
 * Two guarantees the session relies on:
 *
 * - **A query always settles.** A deadline turns into a retryable failure, and
 *   cancelling resolves the dispatch even if the provider's own promise hangs
 *   forever. Nothing can hold a group open indefinitely.
 * - **A group is published whole.** Its outcomes are reported once every one of
 *   its providers has settled, successes and failures together, so a group
 *   never appears half-filled and then grows.
 */
import {
    SEARCH_DYNAMIC_DEADLINE_MS,
    SEARCH_DYNAMIC_DEBOUNCE_MS,
    type SearchEntry
} from '$lib/kernel/search/types.js';

/** One dispatchable provider query, already bound to a session's query and scope. */
export interface DynamicProviderTask {
    readonly providerId: string;
    readonly groupId: string;
    search(signal: AbortSignal): Promise<readonly SearchEntry[]>;
}

/** How one provider's query ended. Cancelled dispatches are never reported. */
export interface DynamicProviderOutcome {
    readonly providerId: string;
    readonly groupId: string;
    readonly status: 'fulfilled' | 'failed';
    readonly entries: readonly SearchEntry[];
    /** Present on `failed`; shown beside the provider's retry affordance. */
    readonly message?: string;
}

export interface DynamicRunnerCallbacks {
    /** Every provider of `groupId` has settled for the current generation. */
    onGroupSettled(groupId: string, outcomes: readonly DynamicProviderOutcome[]): void;
    /** Scheduled or in-flight work started or stopped. */
    onPendingChange(pending: boolean): void;
}

export interface DynamicRunnerTiming {
    readonly debounceMs: number;
    readonly deadlineMs: number;
}

/** Injectable so tests can drive the debounce and the deadline without waiting. */
export interface SchedulerTimers {
    setTimeout(handler: () => void, ms: number): number;
    clearTimeout(handle: number): void;
}

export const DEFAULT_RUNNER_TIMING: DynamicRunnerTiming = {
    debounceMs: SEARCH_DYNAMIC_DEBOUNCE_MS,
    deadlineMs: SEARCH_DYNAMIC_DEADLINE_MS
};

export const DEFAULT_SCHEDULER_TIMERS: SchedulerTimers = {
    setTimeout: (handler, ms) => setTimeout(handler, ms) as unknown as number,
    clearTimeout: handle => clearTimeout(handle)
};

/**
 * A FIFO permit pool. A finished task hands its permit straight to the next
 * waiter instead of releasing and re-acquiring it, so the limit holds even when
 * a new task is queued in the same tick a running one completes.
 */
export class ConcurrencyGate {
    private readonly limit: number;
    private readonly waiting: (() => void)[] = [];
    private active = 0;

    public constructor(limit: number) {
        this.limit = Math.max(1, limit);
    }

    /** How many tasks hold a permit right now. Exposed for tests and diagnostics. */
    public get inFlight(): number {
        return this.active;
    }

    public run<T>(task: () => Promise<T>): Promise<T> {
        return this.acquire().then(release => task().then(
            value => {
                release();
                return value;
            },
            error => {
                release();
                throw error;
            }
        ));
    }

    private acquire(): Promise<() => void> {
        const release = () => {
            const next = this.waiting.shift();
            if (next) {
                next();
                return;
            }
            this.active--;
        };
        if (this.active < this.limit) {
            this.active++;
            return Promise.resolve(release);
        }
        return new Promise(resolve => this.waiting.push(() => resolve(release)));
    }
}

export class DynamicQueryRunner {
    private readonly gate: ConcurrencyGate;
    private readonly callbacks: DynamicRunnerCallbacks;
    private readonly timing: DynamicRunnerTiming;
    private readonly timers: SchedulerTimers;

    /** Dispatch id → its controller. Ids are unique so a cancelled dispatch cannot evict its successor. */
    private readonly inFlight = new Map<number, AbortController>();
    /** Group id → how many providers were dispatched for it in this generation. */
    private readonly expected = new Map<string, number>();
    /** Group id → the outcomes collected so far in this generation. */
    private readonly settled = new Map<string, DynamicProviderOutcome[]>();

    private generation = 0;
    private dispatchId = 0;
    private debounceHandle: number | null = null;
    private queued: readonly DynamicProviderTask[] | null = null;
    private suspended = false;
    private disposed = false;
    private lastPending = false;

    public constructor(
        gate: ConcurrencyGate,
        callbacks: DynamicRunnerCallbacks,
        timing: DynamicRunnerTiming = DEFAULT_RUNNER_TIMING,
        timers: SchedulerTimers = DEFAULT_SCHEDULER_TIMERS
    ) {
        this.gate = gate;
        this.callbacks = callbacks;
        this.timing = timing;
        this.timers = timers;
    }

    /** Whether a dispatch is scheduled, held back by a suspension, or in flight. */
    public get pending(): boolean {
        return this.debounceHandle !== null || this.queued !== null || this.inFlight.size > 0;
    }

    /** Cancels the previous batch and debounces a new one. An empty batch just cancels. */
    public start(tasks: readonly DynamicProviderTask[]): void {
        this.cancel();
        if (this.disposed || tasks.length === 0) {
            this.notifyPending();
            return;
        }
        this.queued = tasks;
        this.arm();
        this.notifyPending();
    }

    /**
     * Re-runs one provider for the *current* input, so it keeps the current
     * generation. Its group re-opens until the retry settles and is then
     * reported whole again.
     */
    public retry(task: DynamicProviderTask): void {
        if (this.disposed) {
            return;
        }
        if (!this.expected.has(task.groupId)) {
            this.expected.set(task.groupId, 1);
        }
        const outcomes = this.settled.get(task.groupId);
        if (outcomes) {
            this.settled.set(task.groupId, outcomes.filter(outcome => outcome.providerId !== task.providerId));
        }
        void this.dispatch(task, this.generation);
        this.notifyPending();
    }

    /** Holds back dispatch, e.g. while an IME composition is open. Resuming flushes at once. */
    public suspend(suspended: boolean): void {
        if (this.disposed || this.suspended === suspended) {
            return;
        }
        this.suspended = suspended;
        if (suspended) {
            this.clearDebounce();
        } else if (this.queued !== null) {
            // The user already waited through the composition; do not make them
            // wait out another debounce for text they finished typing.
            this.flush();
        }
        this.notifyPending();
    }

    /** Drops every scheduled and in-flight query and invalidates their replies. */
    public cancel(): void {
        this.generation++;
        this.clearDebounce();
        this.queued = null;
        this.expected.clear();
        this.settled.clear();
        const controllers = [...this.inFlight.values()];
        this.inFlight.clear();
        for (const controller of controllers) {
            controller.abort();
        }
        this.notifyPending();
    }

    public dispose(): void {
        this.cancel();
        this.disposed = true;
        this.notifyPending();
    }

    private arm(): void {
        if (this.disposed || this.suspended || this.queued === null || this.debounceHandle !== null) {
            return;
        }
        this.debounceHandle = this.timers.setTimeout(() => {
            this.debounceHandle = null;
            this.flush();
            this.notifyPending();
        }, this.timing.debounceMs);
    }

    private flush(): void {
        const tasks = this.queued;
        this.queued = null;
        if (this.disposed || !tasks) {
            return;
        }
        // Counted before anything is dispatched, so a group is never reported
        // settled while siblings are still being handed to the gate.
        for (const task of tasks) {
            this.expected.set(task.groupId, (this.expected.get(task.groupId) ?? 0) + 1);
        }
        const generation = this.generation;
        for (const task of tasks) {
            void this.dispatch(task, generation);
        }
    }

    private async dispatch(task: DynamicProviderTask, generation: number): Promise<void> {
        const id = this.dispatchId++;
        const controller = new AbortController();
        this.inFlight.set(id, controller);

        let outcome: DynamicProviderOutcome | null = null;
        try {
            outcome = await this.gate.run(() => this.awaitProvider(task, controller));
        } catch (error) {
            outcome = failure(task, messageOf(error));
        } finally {
            this.inFlight.delete(id);
        }

        // A superseded generation, a cancelled dispatch or a disposed runner all
        // mean the reply is worthless — even when the transport delivered it.
        if (!this.disposed && outcome !== null && generation === this.generation) {
            this.record(outcome);
        }
        this.notifyPending();
    }

    /**
     * Resolves with the provider's outcome, `null` when the dispatch was
     * cancelled, and a failure when the deadline passed. Always resolves, even
     * if the provider ignores its signal and never settles its own promise.
     */
    private awaitProvider(task: DynamicProviderTask, controller: AbortController): Promise<DynamicProviderOutcome | null> {
        if (controller.signal.aborted) {
            return Promise.resolve(null);
        }
        return new Promise<DynamicProviderOutcome | null>(resolve => {
            let deadline: number | null = null;
            let done = false;
            const settle = (value: DynamicProviderOutcome | null) => {
                if (done) {
                    return;
                }
                done = true;
                if (deadline !== null) {
                    this.timers.clearTimeout(deadline);
                }
                resolve(value);
            };

            deadline = this.timers.setTimeout(() => {
                // Settle first: the abort below must not be mistaken for a
                // cancellation, a deadline is a retryable failure.
                settle(failure(task, `Search provider "${task.providerId}" did not answer within ${this.timing.deadlineMs} ms.`));
                controller.abort();
            }, this.timing.deadlineMs);

            controller.signal.addEventListener('abort', () => settle(null), {once: true});

            try {
                Promise.resolve(task.search(controller.signal)).then(
                    entries => settle({
                        providerId: task.providerId,
                        groupId: task.groupId,
                        status: 'fulfilled',
                        entries: [...(entries ?? [])]
                    }),
                    error => settle(failure(task, messageOf(error)))
                );
            } catch (error) {
                settle(failure(task, messageOf(error)));
            }
        });
    }

    private record(outcome: DynamicProviderOutcome): void {
        const previous = this.settled.get(outcome.groupId) ?? [];
        const outcomes = [...previous.filter(entry => entry.providerId !== outcome.providerId), outcome];
        this.settled.set(outcome.groupId, outcomes);
        if (outcomes.length >= (this.expected.get(outcome.groupId) ?? 0)) {
            this.callbacks.onGroupSettled(outcome.groupId, outcomes);
        }
    }

    private clearDebounce(): void {
        if (this.debounceHandle !== null) {
            this.timers.clearTimeout(this.debounceHandle);
            this.debounceHandle = null;
        }
    }

    private notifyPending(): void {
        const pending = !this.disposed && this.pending;
        if (pending === this.lastPending) {
            return;
        }
        this.lastPending = pending;
        this.callbacks.onPendingChange(pending);
    }
}

function failure(task: DynamicProviderTask, message: string): DynamicProviderOutcome {
    return {providerId: task.providerId, groupId: task.groupId, status: 'failed', entries: [], message};
}

function messageOf(error: unknown): string {
    if (error instanceof Error && error.message !== '') {
        return error.message;
    }
    return typeof error === 'string' && error !== '' ? error : 'The search request failed.';
}
