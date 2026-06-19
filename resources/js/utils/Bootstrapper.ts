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

    public onStageReached(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_BEFORE, handler);
        return this.reached.on(stage, handler);
    }

    public onStage(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_BEFORE, handler);
        return this.stages.on(stage, handler);
    }

    public onPreparationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_PREPARATION, handler);
    }

    public onMigrationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_MIGRATION, handler);
    }

    public onEarlyStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_EARLY, handler);
    }

    public onMainStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_MAIN, handler);
    }

    public onLateStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_LATE, handler);
    }

    public onFinalizationStage(handler: BootstrapHandler): () => void {
        return this.onStage(BOOT_STAGE_FINALIZATION, handler);
    }

    public onStagePassed(stage: BootStage, handler: BootstrapHandler): () => void {
        this.runImmediatelyIfAlreadyPassed(stage, TIMING_AFTER, handler);
        return this.passed.on(stage, handler);
    }

    public run(): Promise<void> {
        console.log('Bootstrapper starting run sequence');
        if (this._runPromise) {
            return this._runPromise;
        }

        this._runPromise = new Promise<void>(async (resolve) => {
            for (const stage of bootStages) {
                console.log('RUNNING BOOTSTRAP STAGE', stage);
                this._currentStage = stage;
                this._currentStateTiming = TIMING_BEFORE;
                await this.reached.trigger(stage, this);
                this._currentStateTiming = TIMING_RUNNING;
                await this.stages.trigger(stage, this);
                this._currentStateTiming = TIMING_AFTER;
                await this.passed.trigger(stage, this);
                this._currentStateTiming = TIMING_DONE;
            }

            resolve();
        });

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
