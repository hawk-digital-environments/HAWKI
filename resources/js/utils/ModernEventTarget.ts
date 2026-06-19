export class ModernEventTarget<TEvents extends Record<string, any> = Record<string, any>> {
    private eventTarget = new EventTarget();

    public on<TType extends keyof TEvents>(type: TType, handler: (detail: TEvents[TType]) => void): () => void {
        const listener = (event: Event) => {
            handler((event as CustomEvent<TEvents[TType]>).detail);
        };
        this.eventTarget.addEventListener(type as string, listener);
        return () => {
            this.eventTarget.removeEventListener(type as string, listener);
        };
    }

    public trigger<TType extends keyof TEvents>(
        type: TType,
        ...args: TEvents[TType] extends void ? [] : [detail: TEvents[TType]]
    ): void {
        const detail = args[0];
        this.eventTarget.dispatchEvent(new CustomEvent(type as string, {detail}));
    }
}
