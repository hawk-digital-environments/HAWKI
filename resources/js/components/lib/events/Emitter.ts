/**
 * Minimal typed event emitter, generic over an event-map record — the same
 * ergonomics as the host app's `SyncPipeline<Events>`
 * (`resources/js/utils/flows/SyncPipeline.ts`), without the dependency on it.
 *
 * Deliberately not built on `EventTarget`/`CustomEvent`: that loses the
 * generic event-map typing and forces `detail` casting at every call site.
 *
 * Implements only what this package's components need — registering a
 * handler and emitting an event to all registered handlers.
 *
 * @example
 * interface Events {
 *     focusCitation: string;
 * }
 *
 * const emitter = new Emitter<Events>();
 * const off = emitter.on('focusCitation', (id) => console.log(id));
 * emitter.emit('focusCitation', 'abc');
 * off(); // unregister
 */
export class Emitter<TEvents extends Record<string, any>> {
    private handlers = {} as { [K in keyof TEvents]?: Array<(payload: TEvents[K]) => void> };

    /**
     * Registers `handler` for `key`. Returns a cleanup function that removes
     * the handler when called; the returned function is idempotent and safe
     * to call more than once.
     */
    public on<K extends keyof TEvents>(key: K, handler: (payload: TEvents[K]) => void): () => void {
        const list = this.handlers[key] ?? (this.handlers[key] = []);
        list.push(handler);

        return () => {
            const items = this.handlers[key];
            if (items) {
                this.handlers[key] = items.filter(i => i !== handler) as any;
            }
        };
    }

    /**
     * Calls all handlers registered for `key`, in registration order, with
     * `payload`.
     */
    public emit<K extends keyof TEvents>(key: K, payload: TEvents[K]): void {
        for (const handler of this.handlers[key] ?? []) {
            handler(payload);
        }
    }
}
