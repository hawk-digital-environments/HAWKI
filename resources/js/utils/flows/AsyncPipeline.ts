import {AbstractFlow} from '$lib/utils/flows/AbstractFlow.js';
import type {RecordKeys} from '$lib/types.js';

type AsyncHandler<TData = any> = (data: TData) => Promise<void> | void;

/**
 * Typed pipeline for async-capable handlers.
 *
 * Handlers registered via {@link on} are awaited one by one in registration
 * order when {@link trigger} fires. Each handler must finish before the next
 * one starts, which makes this suitable for ordered initialization sequences
 * or any case where step N may depend on step N-1 being complete.
 *
 * When multiple handlers can run concurrently, use {@link ParallelAsyncWorkflow}
 * instead.
 *
 * @example
 * interface Events {
 *     beforeSave: { data: FormData };
 *     afterSave: void;
 * }
 *
 * const pipeline = new AsyncPipeline<Events>();
 *
 * const off = pipeline.on('beforeSave', async ({ data }) => {
 *     await validate(data);
 * });
 * await pipeline.trigger('beforeSave', { data: formData });
 * off(); // unregister
 */
export class AsyncPipeline<TItems extends Record<string, any>> extends AbstractFlow<RecordKeys<TItems>, AsyncHandler> {
    /**
     * Registers a handler for the given event type. The handler may be async
     * or synchronous. Returns a cleanup function that removes the handler.
     */
    public on<TType extends RecordKeys<TItems>>(
        type: TType,
        handler: AsyncHandler<TItems[TType]>
    ): () => void {
        return this.track(type, handler);
    }

    /**
     * Awaits each handler for `type` in registration order, then resolves to
     * the data that was passed in.
     */
    public async trigger<TType extends RecordKeys<TItems>>(
        type: TType,
        ...args: TItems[TType] extends void ? [] : [data: TItems[TType]]
    ) {
        const data = args[0];

        for (const handler of this.getItems(type)) {
            await handler(data);
        }

        return data as TItems[TType] extends void ? undefined : TItems[TType];
    }

    /** Like {@link trigger}, but resolves to `void`. Use when a `Promise<void>`
     * return type is required at the call site and the data is not needed. */
    public triggerVoid<TType extends RecordKeys<TItems>>(
        type: TType,
        ...args: TItems[TType] extends void ? [] : [data: TItems[TType]]
    ): Promise<void> {
        return this.trigger(type, ...(args as any)).then(() => void 0);
    }
}
