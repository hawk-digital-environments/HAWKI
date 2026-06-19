type HookHandler<TData = any> = (data: TData) => Promise<TData> | TData;

interface HookHandlerEntry<TData = any> {
    handler: HookHandler<TData>;
}

export class HookQueue<THooks extends Record<string, any> = Record<string, any>> {
    private hooks: Partial<Record<keyof THooks, Array<HookHandlerEntry>>> = {};

    public on<TType extends keyof THooks>(type: TType, handler: HookHandler<THooks[TType]>): () => void {
        if (!this.hooks[type]) {
            this.hooks[type] = [];
        }
        this.hooks[type].push({handler});

        return () => {
            const handlers = this.hooks[type];
            if (handlers) {
                this.hooks[type] = handlers.filter(entry => entry.handler !== handler);
            }
        };
    }

    public onResultless<TType extends keyof THooks>(type: TType, handler: (data: THooks[TType]) => void | Promise<void>): () => void {
        return this.on(type, async (data) => {
            await handler(data);
            return data;
        });
    }

    public async trigger<TType extends keyof THooks>(type: TType, data: THooks[TType]): Promise<THooks[TType]> {
        const handlers = this.hooks[type] || [];
        let result = data;
        for (const {handler} of handlers) {
            result = await handler(result);
        }
        return result;
    }
}
