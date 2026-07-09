import type {RecordKeys} from '$lib/types.js';
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';

/**
 * Async workflow that runs handlers concurrently up to a configurable limit.
 *
 * Unlike {@link AsyncPipeline} (which awaits handlers one by one),
 * `ParallelAsyncWorkflow` maintains a live concurrency pool: as soon as one
 * handler finishes, the next starts immediately, keeping up to `chunkSize`
 * handlers in flight at all times. Registration order is still respected in
 * the sense that later-registered handlers only enter the pool once a slot is
 * free, but they do not wait for every earlier handler to complete first.
 *
 * This makes it well suited for independent work within a single stage, such
 * as parallel boot tasks or concurrent data fetches, where some tasks will
 * finish sooner than others and waiting for the slowest one to block the slot
 * would waste time.
 *
 * @example
 * interface Stages {
 *     boot: Bootstrapper;
 * }
 *
 * // Run up to 3 boot tasks concurrently
 * const workflow = new ParallelAsyncWorkflow<Stages>(3);
 *
 * workflow.on('boot', async (ctx) => loadConfig(ctx));
 * workflow.on('boot', async (ctx) => loadSession(ctx));
 * workflow.on('boot', async (ctx) => prefetchRoutes(ctx));
 * workflow.on('boot', async (ctx) => warmCache(ctx)); // starts as soon as any of the above finish
 *
 * await workflow.trigger('boot', bootstrapper);
 */
export class ParallelAsyncWorkflow<TItems extends Record<string, any>> extends AsyncPipeline<TItems> {
    /**
     * @param chunkSize Maximum number of handlers allowed to run concurrently. Defaults to 5.
     */
    constructor(
        private chunkSize: number = 5
    ) {
        super();
    }

    /**
     * Runs all handlers for `type` through a concurrency pool of size
     * {@link chunkSize}. Resolves once every handler has completed.
     */
    public async trigger<TType extends RecordKeys<TItems>>(
        type: TType,
        ...args: TItems[TType] extends void ? [] : [data: TItems[TType]]
    ) {
        const data = args[0];
        const executing = new Set<Promise<void>>();

        for (const handler of this.getItems(type)) {
            const p: Promise<void> = Promise.resolve(handler(data)).then(() => {
                executing.delete(p);
            });
            executing.add(p);

            if (executing.size >= this.chunkSize) {
                await Promise.race(executing);
            }
        }

        await Promise.all(executing);
        return data as TItems[TType] extends void ? undefined : TItems[TType];
    }
}
