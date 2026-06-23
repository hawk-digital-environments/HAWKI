/**
 * Phased application initialization system.
 *
 * The `Bootstrapper` divides startup into six ordered stages so that
 * different parts of the app can declare *when* their setup must run without
 * needing to know about each other:
 *
 *   preparation → migration → early → main → late → finalization
 *
 * Handlers within a stage run concurrently (via `ParallelAsyncWorkflow`); the
 * next stage does not begin until every handler of the current one has
 * resolved. Pre-stage (`onStageReached`) and post-stage (`onStagePassed`)
 * hooks run serially so they can act as setup/teardown guards.
 *
 * If a handler is registered after its target stage has already passed, it is
 * called immediately and a console warning is emitted — late registration is
 * never silently dropped.
 *
 * The exported `bootstrapper` singleton is the app-wide instance. Call
 * `bootstrapper.run()` once at the entry point to start the sequence.
 *
 * @example
 * import {bootstrapper} from '$lib/utils/Bootstrapper.js';
 *
 * bootstrapper.onMainStage(async () => {
 *     await loadUserSession();
 * });
 *
 * bootstrapper.run();
 */
import {ParallelAsyncWorkflow} from '$lib/utils/flows/ParallelAsyncWorkflow.js';
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';

const BOOT_STAGE_PREPARATION = 'preparation';
const BOOT_STAGE_MIGRATION = 'migration';
const BOOT_STAGE_EARLY = 'early';
const BOOT_STAGE_MAIN = 'main';
const BOOT_STAGE_LATE = 'late';
const BOOT_STAGE_FINALIZATION = 'finalization';

const bootStages = [BOOT_STAGE_PREPARATION, BOOT_STAGE_MIGRATION, BOOT_STAGE_EARLY, BOOT_STAGE_MAIN, BOOT_STAGE_LATE, BOOT_STAGE_FINALIZATION] as const;
export type BootStage = typeof bootStages[number];

const TIMING_NOT_STARTED = 'not-started';
const TIMING_BEFORE = 'before';
const TIMING_RUNNING = 'running';
const TIMING_AFTER = 'after';
const TIMING_DONE = 'done';

const timings = [TIMING_NOT_STARTED, TIMING_BEFORE, TIMING_RUNNING, TIMING_AFTER, TIMING_DONE] as const;
export type BootTiming = typeof timings[number];

type BootstrapHandler = (bootstrap: Bootstrapper) => void | Promise<void>;

interface FlowList {
    [BOOT_STAGE_PREPARATION]: Bootstrapper;
    [BOOT_STAGE_MIGRATION]: Bootstrapper;
    [BOOT_STAGE_EARLY]: Bootstrapper;
    [BOOT_STAGE_MAIN]: Bootstrapper;
    [BOOT_STAGE_LATE]: Bootstrapper;
    [BOOT_STAGE_FINALIZATION]: Bootstrapper;
}

export class Bootstrapper {
    private reached = new AsyncPipeline<FlowList>();
    private stages = new ParallelAsyncWorkflow<FlowList>(3);
    private passed = new AsyncPipeline<FlowList>();

    private _currentStage: BootStage = BOOT_STAGE_PREPARATION;
    private _currentStateTiming: BootTiming = TIMING_NOT_STARTED;
    private _runPromise: Promise<void> | null = null;

    public get currentStage(): BootStage {
        return this._currentStage;
    }

    /** Registers a handler that runs *before* `stage` starts (serial). Use to
     *  set up preconditions that the stage's parallel handlers depend on. */
    public onStageReached(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_BEFORE, handler);
        return this.reached.on(stage, handler);
    }

    /** Registers a handler to run *during* `stage` (concurrently with other
     *  handlers for the same stage, up to the concurrency limit). Returns a
     *  cleanup function that deregisters the handler. */
    public onStage(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_BEFORE, handler);
        return this.stages.on(stage, handler);
    }

    /** Shorthand for `onStage('preparation', handler)`. Use for bootstrapping
     *  fundamental infrastructure (config, DI container). */
    public onPreparationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_PREPARATION, handler);
    }

    /** Shorthand for `onStage('migration', handler)`. Use for schema or
     *  storage migrations that must complete before the app starts. */
    public onMigrationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_MIGRATION, handler);
    }

    /** Shorthand for `onStage('early', handler)`. Use for services that other
     *  `main`-stage work depends on (e.g. auth, feature flags). */
    public onEarlyStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_EARLY, handler);
    }

    /** Shorthand for `onStage('main', handler)`. The primary initialization
     *  stage — most feature setup goes here. */
    public onMainStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_MAIN, handler);
    }

    /** Shorthand for `onStage('late', handler)`. Use for work that should only
     *  run after all main features are ready (e.g. analytics, telemetry). */
    public onLateStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_LATE, handler);
    }

    /** Shorthand for `onStage('finalization', handler)`. The last stage — use
     *  for cleanup, final UI rendering, or marking the app as ready. */
    public onFinalizationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_FINALIZATION, handler);
    }

    /** Registers a handler that runs *after* `stage` completes (serial). Use
     *  to react to a stage finishing without blocking the next one's start. */
    public onStagePassed(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_AFTER, handler);
        return this.passed.on(stage, handler);
    }

    /** Runs all stages in order. Idempotent — subsequent calls return the
     *  same promise as the first call. */
    public run(): Promise<void> {
        if (this._runPromise) {
            return this._runPromise;
        }

        this._runPromise = (async () => {
            for (const stage of bootStages) {
                this._currentStage = stage;
                this._currentStateTiming = TIMING_BEFORE;
                await this.reached.trigger(stage, this);
                this._currentStateTiming = TIMING_RUNNING;
                await this.stages.trigger(stage, this);
                this._currentStateTiming = TIMING_AFTER;
                await this.passed.trigger(stage, this);
                this._currentStateTiming = TIMING_DONE;
            }
        })();

        return this._runPromise;
    }

    private runImmediatelyIfAlreadyPassed(stage: BootStage, timing: BootTiming, handler: BootstrapHandler) {
        if (this._currentStateTiming === TIMING_NOT_STARTED) {
            return false;
        }

        const isAlreadyPassedOrIn = bootStages.indexOf(stage) <= bootStages.indexOf(this._currentStage);
        if (!isAlreadyPassedOrIn) {
            return false;
        }

        const isInStage = stage === this._currentStage;
        const currentTimingIdx = timings.indexOf(this._currentStateTiming);
        const timingIdx = timings.indexOf(timing);

        // <= because while the timing is executing we can no longer push handlers to it, so we consider it passed for registration purposes
        if (isInStage && currentTimingIdx <= timingIdx) {
            return false;
        }

        console.warn(`Trying to register a bootstrap handler for stage ${stage} and timing ${timing}, but that timing has already passed. Running handler immediately.`);
        void handler(this);
        return true;
    }
}

export const bootstrapper = new Bootstrapper();
