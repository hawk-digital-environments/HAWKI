import {RestApi} from '$lib/kernel/api/RestApi.js';
import {createDefaultTransport} from '$lib/kernel/api/transport.js';
import {type Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';
import {AiApi} from '$lib/kernel/ai/AiApi.js';
import {ConnectionHandle} from '$lib/kernel/client/connection/ConnectionHandle.svelte.js';
import type {HawkiEvents} from '$lib/kernel/events/EventExtension.js';
import {registerConnectionRefresher} from '$lib/kernel/client/connection/connectionRefresher.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly client: HawkiClient;
        readonly restApi: RestApi;
        readonly aiApi: AiApi;
        readonly linkPreviewApi: LinkPreviewApi;
        readonly uriBuilder: UriBuilder;
        readonly connection: Connection;
        readonly connectionOrNull: Connection | null;
        readonly isAuthenticatedConnection: boolean;

        refreshConnection(): Promise<Connection>;

        /**
         * Fires the async `logout` event (so listeners — keychain, passkey
         * session — clear local state) and then hard-redirects to the backend
         * logout URL in a `finally`. The redirect happens regardless of a
         * listener throwing, otherwise a failing cleanup would strand the
         * user on an authenticated page.
         */
        logout(): Promise<void>;
    }

    /** Fired by {@link ClientExtension.logout} before the redirect; listeners drop any in-memory secrets / local session here. */
    interface HawkiAsyncEvents {
        logout: void;
    }
}

// @todo this extension is not really settled and WILL be refactored/changed in the future. Don't rely on it yet.

export class ClientExtension implements HawkiAppExtension {
    private readonly connectionHandle: ConnectionHandle;
    private resourceSchemas: HawkiAppExtensions['resourceSchemas'] | null = null;

    public readonly uriBuilder = new UriBuilder(window.location.origin);
    public readonly client: HawkiClient;

    public constructor(private readonly events: HawkiEvents) {
        const transport = createDefaultTransport();
        const getConnection = () => this.connectionHandle.connection;
        const restApi = new RestApi(
            this.uriBuilder,
            transport,
            getConnection,
            (resourceType: string) => {
                if (!this.resourceSchemas) {
                    throw new Error('Resource schemas have not been loaded yet');
                }
                return this.resourceSchemas.get(resourceType);
            }
        );
        this.connectionHandle = new ConnectionHandle(restApi, events);
        this.client = {
            restApi: restApi,
            aiApi: new AiApi({transport}),
            get connection() {
                return getConnection();
            }
        };
    }

    private getConnection(): Connection {
        return this.connectionHandle.connection;
    }

    public async refreshConnection(): Promise<Connection> {
        return this.connectionHandle.refreshConnection();
    }

    public async logout() {
        try {
            await this.events?.async.trigger('logout');
        } finally {
            window.location.assign(this.uriBuilder.logoutUri());
        }
    }

    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void {
        this.resourceSchemas = app.getOrFail('resourceSchemas');
        bootstrapper.onPreparationStage(async () => this.connectionHandle.refreshConnection().then());
        registerConnectionRefresher(this.connectionHandle);

        // Public config is connection-dependent. Preserve the initial bootstrap
        // ordering, but refresh it when an established session changes type
        // (for example internal_registering_user -> internal_authenticated).
        this.events.async.on('connectionChanged', async () => {
            await app.config?.refresh();
        });
    }

    public provideProperties(): Record<string, unknown> {
        const extension = this;
        return {
            get client(): HawkiClient {
                return extension.client;
            },
            get restApi(): RestApi {
                return extension.client.restApi;
            },
            get aiApi(): AiApi {
                return extension.client.aiApi;
            },
            get uriBuilder(): UriBuilder {
                return extension.uriBuilder;
            },
            get connection(): Connection {
                return extension.getConnection();
            },
            // Null-safe counterpart to `connection`: `null` before the initial
            // load completes (and after a refresh that gave up). Guards that
            // run during bootstrap read this instead of `connection`, which
            // throws on an unloaded handle.
            get connectionOrNull(): Connection | null {
                return extension.connectionHandle.tryGetConnection();
            },
            // `connection.type === 'internal_authenticated'` that never throws
            // — treats "not loaded yet" as simply "not authenticated", so
            // templates and guards can read it unconditionally.
            get isAuthenticatedConnection(): boolean {
                try {
                    return extension.getConnection().type === 'internal_authenticated';
                } catch {
                    return false;
                }
            },
            refreshConnection: () => extension.refreshConnection(),
            logout: () => extension.logout()
        };
    }
}
