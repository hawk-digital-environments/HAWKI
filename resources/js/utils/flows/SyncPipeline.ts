import {AbstractFlow} from '$lib/utils/flows/AbstractFlow.js';
import type {RecordKeys} from '$lib/types.js';

type SyncHandler<TData = any> = (data: TData) => void;

/**
 * Typed pipeline for synchronous handlers.
 *
 * Handlers registered via {@link on} are called in registration order when
 * {@link trigger} fires. The handler type explicitly forbids returning a
 * promise, which prevents the silent bug of an `async` handler being
 * registered here and its work never being awaited. Use {@link AsyncPipeline}
 * when handlers need to be async.
 *
 * @example
 * interface Events {
 *     userLoggedIn: { userId: string };
 *     userLoggedOut: void;
 * }
 *
 * const pipeline = new SyncPipeline<Events>();
 *
 * const off = pipeline.on('userLoggedIn', ({ userId }) => console.log(userId));
 * pipeline.trigger('userLoggedIn', { userId: '42' });
 * off(); // unregister
 */
export class SyncPipeline<TItems extends Record<string, any>> extends AbstractFlow<RecordKeys<TItems>, SyncHandler> {
    /**
     * Registers a synchronous handler for the given event type.
     * Returns a cleanup function that removes the handler when called.
     */
    public on<TType extends RecordKeys<TItems>>(
        type: TType,
        handler: SyncHandler<TItems[TType]>
    ): () => void {
        return this.track(type, handler);
    }

    /**
     * Calls all handlers registered for `type` in order and returns the data
     * that was passed in. Useful when the caller wants to keep a reference to
     * the data after the pipeline has run.
     */
    public trigger<TType extends RecordKeys<TItems>>(
        type: TType,
        ...args: TItems[TType] extends void ? [] : [data: TItems[TType]]
    ) {
        const data = args[0];

        for (const handler of this.getItems(type)) {
            handler(data);
        }

        return data as TItems[TType] extends void ? undefined : TItems[TType];
    }

    /** Like {@link trigger}, but explicitly returns `void`. Useful when the
     * return value is not needed and a void-typed call site is expected. */
    public triggerVoid<TType extends RecordKeys<TItems>>(
        type: TType,
        ...args: TItems[TType] extends void ? [] : [data: TItems[TType]]
    ): void {
        this.trigger(type, ...(args as any));
    }
}
