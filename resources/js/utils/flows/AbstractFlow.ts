/**
 * Base class for all pipeline and workflow variants.
 *
 * Maintains a map of named handler lists and provides the two primitives
 * every subclass needs: registering a handler and retrieving the list.
 * Not intended to be used directly — extend {@link SyncPipeline},
 * {@link AsyncPipeline}, or {@link ParallelAsyncWorkflow} instead.
 */
export abstract class AbstractFlow<TType extends string, THandler> {
    private handlersByType = {} as Record<TType, Array<THandler>>;

    /**
     * Registers `handler` under `type` and returns a cleanup function that
     * removes it. The returned function is idempotent and safe to call more
     * than once.
     */
    protected track(type: TType, handler: THandler): () => void {
        if (!this.handlersByType[type]) {
            this.handlersByType[type] = [];
        }
        this.handlersByType[type].push(handler);

        return () => {
            const items = this.handlersByType[type];
            if (items) {
                this.handlersByType[type] = items.filter(i => i !== handler);
            }
        };
    }

    protected getItems(type: TType): Array<THandler> {
        return this.handlersByType[type] || [];
    }
}
