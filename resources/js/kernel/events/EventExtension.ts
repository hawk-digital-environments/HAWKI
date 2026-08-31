import type {HawkiAppExtension} from '$lib/kernel/HawkiApp.js';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';
import type {HawkiAsyncEvents, HawkiSyncEvents} from '$lib/kernel/extendableTypes.js';
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly events: HawkiEvents;
    }
}

/**
 * The app's event bus, exposed as `app.events`.
 *
 * Split into a {@link sync} pipeline (handlers run inline, in registration
 * order, on the caller's stack) and an {@link async} one (handlers are
 * awaited sequentially). Which one a given event lives on is part of its
 * contract: a handler that must block the caller (e.g. clearing local
 * session state before logout redirects) registers on `sync`; one that does
 * I/O and can race the next line registers on `async`. The two pipelines are
 * independent — a `sync` event does not wait on an `async` one and vice versa.
 */
export interface HawkiEvents {
    sync: SyncPipeline<HawkiSyncEvents>;
    async: AsyncPipeline<HawkiAsyncEvents>;
}

/**
 * App extension that installs {@link HawkiEvents} as `app.events`.
 *
 * Constructor takes optional pipelines so a host that wants a shared bus
 * across multiple apps (or a custom pipeline in tests) can plug one in; the
 * default creates fresh, isolated pipelines.
 */
export class EventExtension implements HawkiAppExtension {
    private readonly sync: SyncPipeline<HawkiSyncEvents>;
    private readonly async: AsyncPipeline<HawkiAsyncEvents>;

    constructor(
        sync?: SyncPipeline<HawkiSyncEvents>,
        async?: AsyncPipeline<HawkiAsyncEvents>
    ) {
        this.sync = sync ?? new SyncPipeline<HawkiSyncEvents>();
        this.async = async ?? new AsyncPipeline<HawkiAsyncEvents>();
    }

    public get events(): HawkiEvents {
        return {
            sync: this.sync,
            async: this.async
        };
    }

    public provideProperties(): Record<string, any> {
        return {
            events: this.events
        };
    }
}
