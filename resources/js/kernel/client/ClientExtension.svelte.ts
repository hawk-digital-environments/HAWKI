import {RestApi} from '$lib/kernel/api/RestApi.js';
import {createDefaultTransport} from '$lib/kernel/api/transport.js';
import {type Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {
    Connection,
    InternalAuthenticatedConnection,
    InternalRegisteringUserConnection
} from '$lib/app/schemas/resources/connections.schema.js';
import {AiApi} from '$lib/kernel/ai/AiApi.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly client: HawkiClient;
        readonly restApi: RestApi;
        readonly aiApi: AiApi;
        readonly linkPreviewApi: LinkPreviewApi;
        readonly uriBuilder: UriBuilder;
        readonly connection: Connection;
        readonly authenticatedConnection: InternalAuthenticatedConnection;
        readonly connectionWithUserInfo: InternalAuthenticatedConnection | InternalRegisteringUserConnection;
        refreshConnection(): Promise<Connection>;
        logout(): void;
    }
}

// @todo this extension is not really settled and WILL be refactored/changed in the future. Don't rely on it yet.

export class ClientExtension implements HawkiAppExtension {
    private currentConnection = $state<Connection | null>(null);
    private resourceSchemas: HawkiAppExtensions['resourceSchemas'] | null = null;
    private app: UnfinishedHawkiApp | null = null;

    public readonly uriBuilder = new UriBuilder(window.location.origin);
    public readonly client: HawkiClient;

    public constructor() {
        const transport = createDefaultTransport();
        const getConnection = () => this.getConnection();
        this.client = {
            restApi: new RestApi(
                this.uriBuilder,
                transport,
                getConnection,
                (resourceType: string) => {
                    if (!this.resourceSchemas) {
                        throw new Error('Resource schemas have not been loaded yet');
                    }
                    return this.resourceSchemas.get(resourceType);
                }
            ),
            aiApi: new AiApi({transport}),
            get connection() {
                return getConnection();
            }
        };
    }

    private getConnection(): Connection {
        if (this.currentConnection === null) {
            throw new Error('Connection has not been loaded yet');
        }
        return this.currentConnection;
    }

    public async refreshConnection(): Promise<Connection> {
        const previousType = this.currentConnection?.type;
        this.currentConnection = await this.client.restApi.getResource('connections', 'hawki');

        // Public config is connection-dependent. Preserve the initial bootstrap
        // ordering, but refresh it when an established session changes type
        // (for example internal_registering_user -> internal_authenticated).
        if (previousType && previousType !== this.currentConnection.type) {
            await this.app?.config?.refresh();
        }
        return this.currentConnection;
    }

    public logout(): void {
        if (this.app?.stores?.has('keychain')) {
            this.app.stores.get('keychain').clearLocalSession();
        }
        this.app?.passkeySession?.clear();
        window.location.assign(this.uriBuilder.logoutUri());
    }

    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void {
        this.app = app;
        this.resourceSchemas = app.getOrFail('resourceSchemas');
        bootstrapper.onPreparationStage(async () => {
            await this.refreshConnection();
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
            get authenticatedConnection(): InternalAuthenticatedConnection {
                const connection = extension.getConnection();
                if (connection.type !== 'internal_authenticated') {
                    throw new Error('Current connection is not authenticated');
                }
                return connection;
            },
            get connectionWithUserInfo(): InternalAuthenticatedConnection | InternalRegisteringUserConnection {
                const connection = extension.getConnection();
                if (connection.type === 'internal_authenticated' || connection.type === 'internal_registering_user') {
                    return connection;
                }
                throw new Error('Current connection does not contain user info');
            },
            refreshConnection: () => extension.refreshConnection(),
            logout: () => extension.logout()
        };
    }
}
