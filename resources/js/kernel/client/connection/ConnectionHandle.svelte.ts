import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';
import type {RestApi} from '$lib/kernel/api/RestApi.js';
import type {HawkiEvents} from '$lib/kernel/events/EventExtension.js';
import {updateObject} from '$lib/utils/objects.js';


/**
 * Async events fired by {@link ConnectionHandle} over `app.events.async`. The
 * `connected` / `connectionChanged` split mirrors "first ever load" vs. "an
 * established session changed type" — listeners that only care about the
 * current value react to both, while ones that bootstrap once (e.g. kick off
 * a background refresh) key off `connected` alone.
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAsyncEvents {
        /** Fired once on the first successful load of the connection. */
        connected: Connection;
        /** Fired when an already-loaded connection's `type` changes (e.g. `internal_registering_user` → `internal_authenticated`). */
        connectionChanged: Connection;
        /** Fired for each failed refresh that will be retried; carries the backoff so listeners can surface "reconnecting…". */
        connectionRefreshRetry: { error: Error, retryCount: number, delay: number };
        /** Fired once the retry budget is exhausted (or no prior connection exists to retry against); the handle drops its connection. */
        connectionRefreshFailed: Error;
    }
}

/**
 * Backoff schedule for transient refresh failures. Index 0 is the first retry,
 * so the gaps widen rather than hammering a struggling backend. Sized to match
 * the retry budget in {@link ConnectionHandle.doRefreshConnection} (4 attempts).
 */
const retryDelays = [5000, 30000, 60000, 300000]; // 5s, 30s, 1m, 5m

/** Outcome of a single refresh attempt, before the handle decides what to do with it. */
type DoRefreshConnectionResult = { type: 'connected', connection: Connection }
    | { type: 'connectionUnchanged', connection: Connection }
    | { type: 'connectionChanged', connection: Connection }
    | { type: 'connectionRefreshRetry', error: Error, retryCount: number, delay: number }
    | { type: 'connectionRefreshFailed', error: Error };

/**
 * Owns the current connection resource and the refresh lifecycle around it.
 *
 * A single instance is shared by the rest client (which reads the connection
 * to authenticate requests), the routing auth middleware (which guards on it
 * and re-checks it on background refreshes) and `app.connection*` getters. All
 * of them go through here rather than holding their own copy, so a refresh
 * updates every consumer at once.
 *
 * Reactivity: `connection` is a `$state` field, so Svelte templates that read
 * it through a getter re-render on change. The retry machinery is *not*
 * reactive state — it's plain fields, since nothing renders "retry attempt
 * n" and surfacing it would just invite components to race the timer.
 */
export class ConnectionHandle {
    private currentConnection = $state<Connection | null>(null);
    /** In-flight refresh, used to coalesce concurrent `refreshConnection()` calls into one request. */
    private refreshPromise: Promise<Connection> | null = null;
    private retryCount = 0;
    private retryRefreshTimeout: number | null = null;

    constructor(
        private readonly restApi: RestApi,
        private readonly events: HawkiEvents
    ) {
    }

    public get connection(): Connection {
        if (this.currentConnection === null) {
            throw new Error('Connection has not been loaded yet');
        }
        return this.currentConnection;
    }

    /** Null-safe counterpart to {@link connection}: `null` while the initial load hasn't completed (or after it gave up). */
    public tryGetConnection(): Connection | null {
        return this.currentConnection;
    }

    /**
     * Stores a freshly fetched connection while preserving the identity of the
     * already-exposed object: components holding `const connection =
     * useConnection()` observe the refreshed session instead of a detached
     * snapshot.
     */
    private storeConnection(connection: Connection): Connection {
        if (this.currentConnection === null) {
            this.currentConnection = connection;
        } else {
            updateObject(this.currentConnection, connection);
        }
        return this.currentConnection;
    }

    /**
     * Fetches the connection resource from the backend and reconciles it with
     * the cached one, firing the right async event for the outcome.
     *
     * Concurrent calls share a single request: the first one drives the work,
     * later ones receive the same promise. Retries (when applicable) happen on
     * a timer outside this call's lifetime — the promise a caller awaits
     * resolves with the *current* connection on a retry, not when the retry
     * completes; listeners that need to react to the eventual recovery follow
     * the `connectionRefreshRetry` / `connected` events.
     */
    public async refreshConnection(): Promise<Connection> {
        if (this.refreshPromise !== null) {
            return this.refreshPromise;
        }

        return this.refreshPromise = this.doRefreshConnection()
            .then(async (res) => {
                async function logErrorsInCallback(callback: () => Promise<void>, eventName: string) {
                    try {
                        return await callback();
                    } catch (error) {
                        console.error(`Error triggering ${eventName} event`, error);
                    }
                }

                if (res.type === 'connected') {
                    const connection = this.storeConnection(res.connection);
                    await logErrorsInCallback(
                        () => this.events.async.triggerVoid('connected', connection),
                        'connected'
                    );
                    return connection;
                }

                if (res.type === 'connectionChanged') {
                    const connection = this.storeConnection(res.connection);
                    await logErrorsInCallback(
                        () => this.events.async.triggerVoid('connectionChanged', connection),
                        'connectionChanged'
                    );
                    return connection;
                }

                if (res.type === 'connectionUnchanged') {
                    return this.storeConnection(res.connection);
                }

                if (res.type === 'connectionRefreshRetry') {
                    console.error(`Connection refresh failed. Retrying in ${res.delay / 1000} seconds...`, res.error);
                    await logErrorsInCallback(
                        () => this.events.async.triggerVoid('connectionRefreshRetry', {error: res.error, retryCount: res.retryCount, delay: res.delay}),
                        'connectionRefreshRetry'
                    );
                    return this.currentConnection!;
                }

                if (res.type === 'connectionRefreshFailed') {
                    this.currentConnection = null;
                    await logErrorsInCallback(
                        () => this.events.async.triggerVoid('connectionRefreshFailed', res.error),
                        'connectionRefreshFailed'
                    );
                    throw res.error;
                }

                throw new Error('Unexpected result from doRefreshConnection');
            })
            .finally(() => {
                this.refreshPromise = null;
            });
    }

    private async doRefreshConnection(): Promise<DoRefreshConnectionResult> {
        clearTimeout(this.retryRefreshTimeout ?? undefined);

        const previousType = this.currentConnection?.type;
        try {
            const connection = await this.restApi.getResource('connections', 'hawki');
            this.retryCount = 0;

            if (previousType !== connection.type) {
                return {
                    type: previousType === undefined ? 'connected' : 'connectionChanged',
                    connection: connection
                };
            }

            return {
                type: 'connectionUnchanged',
                connection: connection
            };
        } catch (error) {
            // Only retry when there is a connection the app is already
            // running on: a failure during the initial load is a hard error
            // (nothing to fall back to), while a failure mid-session keeps the
            // old connection live until the backend recovers.
            if (this.retryCount < 4 && this.currentConnection) {
                this.retryCount++;
                const delay = retryDelays[this.retryCount - 1];
                this.retryRefreshTimeout = window.setTimeout(() => {
                    void this.refreshConnection();
                }, delay);
                return {
                    type: 'connectionRefreshRetry',
                    error: error as Error,
                    retryCount: this.retryCount,
                    delay: delay
                };
            }

            this.retryCount = 0;

            return {
                type: 'connectionRefreshFailed',
                error: error as Error
            };
        }
    }
}
