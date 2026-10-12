import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {ApiTransportError} from '$lib/kernel/api/errors.js';
import type {UserFavoriteResource} from '$lib/app/schemas/resources/user-favorites.schema.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly favorites: WithoutAppExtensionInternals<FavoritesExtension>;
    }
}

/** Logical shape of a favorite as exposed to consumers — the wire's `item_type`
 * is mapped back to `type` (see {@link mapResource}). */
export interface UserFavorite {
    /** Logical owner of the favorite (e.g. `'hawki-core'`). */
    namespace: string;
    /** Kind of favorited item (e.g. `'ai-model'`). */
    type: string;
    /** The favorited item's id. */
    identifier: string;
}

/** Internal list entry — keeps the server row id needed for DELETE. */
type StoredFavorite = UserFavorite & {id: string};

/** One coalescing in-flight (or rather: scheduled) write per favorite key. */
interface PendingWrite {
    timer: ReturnType<typeof setTimeout>;
    intent: 'favorite' | 'unfavorite';
    promise: Promise<void>;
    promiseResolve: () => void;
    promiseReject: (error: unknown) => void;
}

/** Debounce window (ms) in which rapid toggles of the same favorite coalesce. */
const WRITE_DEBOUNCE_MS = 500;

/**
 * Fetches, exposes, and mutates the authenticated user's favorites from the
 * `user-favorites` JSON:API resource.
 *
 * A favorite is a set-membership record addressed by the triple
 * **namespace** (logical owner, default `'hawki-core'`), **type** (the kind of
 * item, e.g. `'ai-model'`) and **identifier** (the item's id) — mirroring the
 * backend `UserFavoritesService` 1:1:
 *
 * - {@link isFavorite} / {@link getFavorites} are reactive reads over the
 *   locally cached list.
 * - {@link markAsFavorite} / {@link removeAsFavorite} are **debounced writes**
 *   (500 ms trailing edge, per favorite key): rapid toggling coalesces, and
 *   only the final intent per key is sent. The local list updates only after
 *   the server confirmed — no optimistic updates.
 *
 * **Error policy:** write failures are never swallowed and never reduced to a
 * console warning — the rethrown error's message reflects the actual state
 * (e.g. favoriting while logged out), so consuming components can render a
 * meaningful toast. The only deliberately swallowed failure is a `401` during
 * {@link refresh}: guests are not allowed to read favorites at all, and the
 * boot-time refresh must not break the unauthenticated app.
 */
export class FavoritesExtension implements HawkiAppExtension {
    /** Default namespace — must stay in sync with `UserFavoritesService::DEFAULT_NAMESPACE`. */
    public static readonly DEFAULT_NAMESPACE = 'hawki-core';

    private app: UnfinishedHawkiApp | null = null;
    private favorites = $state<StoredFavorite[]>([]);
    /** Per-favorite-key pending write state (composite key → intent). */
    private pendingWrites = new Map<string, PendingWrite>();

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        this.app = app;

        bootstrapper.onPreparationStage(() => this.refresh());

        // After an authentication transition favorites may have changed on the
        // server: refresh on login, drop the list (and any pending writes) on
        // logout. The handler awaits the refresh so the event pipeline can
        // sequence/observe its completion.
        this.app.getOrFail('events').async.on('connectionChanged', async (connection: Connection) => {
            if (connection.isAuthenticated) {
                await this.refresh();
                return;
            }
            this.favorites = [];
            this.cancelPendingWrites();
        });
    }

    public provideProperties(): Record<string, unknown> {
        return {favorites: this};
    }

    /**
     * Returns whether the authenticated user has favorited the addressed item.
     * Reactive — components calling it observe refreshes and confirmed writes.
     */
    public isFavorite(type: string, identifier: string, namespace: string = FavoritesExtension.DEFAULT_NAMESPACE): boolean {
        return this.findLocal(namespace, type, identifier) !== undefined;
    }

    /**
     * Returns all favorites of the authenticated user, optionally narrowed by
     * type and/or namespace. Unlike the other methods, an omitted namespace
     * here means "no filter" — it lists every namespace.
     */
    public getFavorites(type?: string, namespace?: string): UserFavorite[] {
        return this.favorites.filter(
            (favorite) =>
                (type === undefined || favorite.type === type)
                && (namespace === undefined || favorite.namespace === namespace)
        );
    }

    /**
     * Marks the addressed item as a favorite. Idempotent. The network write is
     * debounced per favorite key: only the final intent within the 500 ms
     * window is sent.
     *
     * @throws Error with a state-reflecting message when the backend rejects
     *         the write (e.g. not logged in). Catch it and render a toast.
     */
    public markAsFavorite(type: string, identifier: string, namespace: string = FavoritesExtension.DEFAULT_NAMESPACE): Promise<void> {
        return this.scheduleWrite('favorite', namespace, type, identifier);
    }

    /**
     * Removes the addressed item from the user's favorites. Idempotent; the
     * network write is debounced like {@link markAsFavorite}'s.
     *
     * @throws Error with a state-reflecting message when the backend rejects
     *         the write. Catch it and render a toast.
     */
    public removeAsFavorite(type: string, identifier: string, namespace: string = FavoritesExtension.DEFAULT_NAMESPACE): Promise<void> {
        return this.scheduleWrite('unfavorite', namespace, type, identifier);
    }

    /**
     * Re-fetches the full favorites list. Swallows a 401 only — guests cannot
     * read favorites, and the boot-time refresh must not break the anonymous
     * app. Any other failure propagates.
     */
    public async refresh(): Promise<void> {
        if (!this.app) {
            throw new Error('FavoritesExtension has not been initialised.');
        }

        const connection = this.app.connectionOrNull;
        if (connection && !connection.isAuthenticated) {
            this.favorites = [];
            return;
        }

        try {
            const collection = await this.app.getOrFail('restApi').getResourceCollection('user-favorites');
            this.favorites = collection.map(FavoritesExtension.mapResource);
        } catch (error) {
            if (hasTransportStatus(error, 401)) {
                this.favorites = [];
                return;
            }
            throw error;
        }
    }

    /**
     * Schedules a debounced write for the favorite key. Each call within the
     * window replaces the pending intent and returns the promise that settles
     * once the coalesced write completes.
     */
    private scheduleWrite(intent: 'favorite' | 'unfavorite', namespace: string, type: string, identifier: string): Promise<void> {
        const key = compositeKey(namespace, type, identifier);
        const existing = this.pendingWrites.get(key);

        if (existing) {
            clearTimeout(existing.timer);
            existing.intent = intent;
            existing.timer = setTimeout(() => {
                this.flushWrite(key).then(existing.promiseResolve, existing.promiseReject);
            }, WRITE_DEBOUNCE_MS);
            return existing.promise;
        }

        let promiseResolve!: () => void;
        let promiseReject!: (error: unknown) => void;
        const promise = new Promise<void>((resolve, reject) => {
            promiseResolve = resolve;
            promiseReject = reject;
        });
        const timer = setTimeout(() => {
            this.flushWrite(key).then(promiseResolve, promiseReject);
        }, WRITE_DEBOUNCE_MS);
        this.pendingWrites.set(key, {timer, intent, promise, promiseResolve, promiseReject});

        return promise;
    }

    /**
     * Cancels every pending debounced write: clears the timers and rejects the
     * callers' promises, so nothing fires against the (no longer valid) session
     * and no caller hangs on an unresolved promise. Used on logout.
     */
    private cancelPendingWrites(): void {
        for (const pending of this.pendingWrites.values()) {
            clearTimeout(pending.timer);
            pending.promiseReject(new Error('The favorite was not saved because you were logged out.'));
        }
        this.pendingWrites.clear();
    }

    /**
     * Executes the final pending intent for the key and updates the local list
     * from the server response.
     */
    private async flushWrite(key: string): Promise<void> {
        const pending = this.pendingWrites.get(key);
        if (!pending) {
            return;
        }
        this.pendingWrites.delete(key);

        const [namespace, type, identifier] = parseCompositeKey(key);
        const restApi = this.app!.getOrFail('restApi');

        if (pending.intent === 'favorite') {
            const created = await restApi.createResource('user-favorites', {
                namespace,
                item_type: type,
                identifier
            });
            const favorite = FavoritesExtension.mapResource(created);
            this.removeLocal(namespace, type, identifier);
            this.favorites = [...this.favorites, favorite];
        } else {
            // The row id is only known from a previous fetch/create. Without a
            // local entry there was never a confirmed favorite, so the server
            // has nothing to delete — removing is a local no-op.
            const local = this.findLocal(namespace, type, identifier);
            if (local) {
                try {
                    await restApi.deleteResource('user-favorites', local.id);
                } catch (error) {
                    // A 404 means the favorite is already gone — idempotent success.
                    if (!hasTransportStatus(error, 404)) {
                        throw error;
                    }
                }
            }
            this.removeLocal(namespace, type, identifier);
        }
    }

    private findLocal(namespace: string, type: string, identifier: string): StoredFavorite | undefined {
        return this.favorites.find(
            (favorite) => favorite.namespace === namespace && favorite.type === type && favorite.identifier === identifier
        );
    }

    private removeLocal(namespace: string, type: string, identifier: string): void {
        this.favorites = this.favorites.filter(
            (favorite) => !(favorite.namespace === namespace && favorite.type === type && favorite.identifier === identifier)
        );
    }

    /**
     * Maps a wire resource (`item_type`, plus the row id) to the logical shape.
     */
    private static mapResource(resource: UserFavoriteResource): StoredFavorite {
        return {
            id: String(resource.id),
            namespace: resource.namespace,
            type: resource.item_type,
            identifier: resource.identifier
        };
    }
}

function hasTransportStatus(error: unknown, status: number): boolean {
    return error instanceof Error && 'status' in error && (error as ApiTransportError).status === status;
}

/**
 * Ambiguity-free composite key for the (`namespace`, `type`, `identifier`) triple —
 * the parts are free-form strings, so a delimiter-joined key could be parsed
 * differently than it was built (e.g. an identifier containing `|`).
 */
function compositeKey(namespace: string, type: string, identifier: string): string {
    return JSON.stringify([namespace, type, identifier]);
}

function parseCompositeKey(key: string): [string, string, string] {
    const [namespace, type, identifier] = JSON.parse(key) as [string, string, string];
    return [namespace, type, identifier];
}
