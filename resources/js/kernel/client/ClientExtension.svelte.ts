import {RestApi} from '$lib/kernel/api/RestApi.js';
import {createDefaultTransport} from '$lib/kernel/api/transport.js';
import {type Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';
import {AiApi} from '$lib/kernel/ai/AiApi.js';
import {ConnectionHandle} from '$lib/kernel/client/connection/ConnectionHandle.svelte.js';
import type {HawkiEvents} from '$lib/kernel/events/EventExtension.js';
import {assignAuthPage, authPageUrl} from '$lib/kernel/auth/navigation.js';
import {LogoutResponseSchema} from '$lib/kernel/auth/schemas.js';
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
        readonly isAuthenticated: boolean;
        readonly cryptoReady: boolean;
        readonly logoutState: 'idle' | 'pending' | 'failed';

        refreshConnection(): Promise<Connection>;

        /** Locks in-memory keys before posting logout; the encrypted browser passkey is retained. */
        logout(): Promise<void>;
    }

    interface HawkiSyncEvents {
        sessionLost: void;
    }

    /** Fired by {@link ClientExtension.logout} before the redirect; listeners drop in-memory secrets here. */
    interface HawkiAsyncEvents {
        logout: void;
    }
}

// @todo this extension is not really settled and WILL be refactored/changed in the future. Don't rely on it yet.

export class ClientExtension implements HawkiAppExtension {
    private readonly connectionHandle: ConnectionHandle;
    private app: HawkiApp | null = null;
    private sessionLost = false;
    private hadUserInfo = false;
    private logoutStatus = $state<'idle' | 'pending' | 'failed'>('idle');
    private resourceSchemas: HawkiAppExtensions['resourceSchemas'] | null = null;

    public readonly uriBuilder = new UriBuilder(window.location.origin);
    public readonly client: HawkiClient;

    public constructor(private readonly events: HawkiEvents) {
        const transport = createDefaultTransport(() => {
            const connection = this.connectionHandle?.tryGetConnection();
            if (this.sessionLost || this.logoutStatus !== 'idle' || !connection?.hasUserInfo) return;
            this.sessionLost = true;
            this.events.sync.trigger('sessionLost');
            if (this.app) assignAuthPage(this.app.router, 'login', undefined, 'session_expired');
        });
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

    public async logout(): Promise<void> {
        if (this.logoutStatus === 'pending') return;
        this.logoutStatus = 'pending';
        this.app?.passkeySession.clear();
        try {
            try {
                await this.events.async.trigger('logout');
            } catch (error) {
                console.error('A logout listener failed.', error);
            }
            const response = await this.client.restApi.postToResourceAction('auth', 'actions/logout', {}, {
                schema: LogoutResponseSchema
            });
            window.location.assign(response.meta.redirect_url ?? authPageUrl(this.app!.router, 'login'));
        } catch (error) {
            this.logoutStatus = 'failed';
            throw error;
        }
    }

    public ready(app: HawkiApp): void {
        this.app = app;
        this.hadUserInfo = this.connectionHandle.tryGetConnection()?.hasUserInfo ?? false;
    }

    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void {
        this.resourceSchemas = app.getOrFail('resourceSchemas');
        bootstrapper.onPreparationStage(async () => this.connectionHandle.refreshConnection().then());
        registerConnectionRefresher(this.connectionHandle);
        this.events.async.on('connected', connection => { this.hadUserInfo = connection.hasUserInfo; });
        this.events.async.on('connectionRefreshFailed', () => {
            if (this.sessionLost || !this.hadUserInfo || this.logoutStatus !== 'idle' || !this.app) return;
            this.sessionLost = true;
            this.events.sync.trigger('sessionLost');
            assignAuthPage(this.app.router, 'login', undefined, 'session_expired');
        });

        // Public config is connection-dependent. Preserve the initial bootstrap
        // ordering, but refresh it when an established session changes type
        // (for example internal_registering_user -> internal_authenticated).
        this.events.async.on('connectionChanged', async connection => {
            // Logout owns navigation until its provider response or a successful retry arrives.
            if (this.sessionLost || this.logoutStatus !== 'idle') return;
            const established = this.hadUserInfo;
            this.hadUserInfo = connection.hasUserInfo;
            if (established && this.app?.isMounted) {
                this.events.sync.trigger('sessionLost');
                const page = connection.isAuthenticated
                    ? connection.keychain_state === 'setup_required' ? 'register'
                    : connection.keychain_state === 'inconsistent' ? 'inconsistent' : 'handshake'
                    : connection.hasUserInfo ? 'register' : 'login';
                if (assignAuthPage(this.app.router, page)) return;
            }
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
            get isAuthenticated(): boolean {
                return extension.connectionHandle.tryGetConnection()?.isAuthenticated ?? false;
            },
            get cryptoReady(): boolean {
                return extension.logoutStatus === 'idle' && !extension.sessionLost &&
                    (extension.app?.passkeySession.cryptoReady ?? false);
            },
            get logoutState() {
                return extension.logoutStatus;
            },
            refreshConnection: () => extension.refreshConnection(),
            logout: () => extension.logout()
        };
    }
}
