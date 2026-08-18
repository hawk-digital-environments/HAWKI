import {RestApi} from '$lib/kernel/api/RestApi.js';
import {createDefaultTransport} from '$lib/kernel/api/transport.js';
import {type Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {Connection, InternalAuthenticatedConnection, InternalRegisteringUserConnection} from '$lib/app/schemas/resources/connections.schema.js';
import {AiApi} from '$lib/kernel/ai/AiApi.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly client: HawkiClient;
        readonly restApi: RestApi;
        readonly aiApi: AiApi;
        readonly linkPreviewApi: LinkPreviewApi;
        readonly uriBuilder: UriBuilder;

        /**
         * Returns the current HAWKI connection information. This includes the API version and user info.
         * The connection must be loaded first by calling `loadConnection()`.
         *
         * @throws Error if the connection has not been loaded yet.
         */
        readonly connection: Connection;

        /**
         * Returns the current HAWKI connection information, but only if the client is authenticated. This includes the API version and user info.
         * The connection must be loaded first by calling `loadConnection()`.
         *
         * @throws Error if the connection has not been loaded yet or if the client is not authenticated.
         */
        readonly authenticatedConnection: InternalAuthenticatedConnection;
        /**
         * Returns the current HAWKI connection information, but only if the client is authenticated or in the process of registering a new user account. This includes the API version and user info.
         * The connection must be loaded first by calling `loadConnection()`.
         *
         * @throws Error if the connection has not been loaded yet or if the client is not authenticated or registering a new user.
         */
        readonly connectionWithUserInfo: InternalAuthenticatedConnection | InternalRegisteringUserConnection;

        /**
         * Re-fetches the connection resource from the backend and replaces the cached
         * {@link connection}. Call this whenever the session state may have changed
         * without a page reload (e.g. after an in-SPA login or a detected logout) so
         * `app.connection` and everything derived from it stays accurate.
         */
        reloadConnection(): Promise<Connection>;
    }
}

// @todo this extension is not really settled and WILL be refactored/changed in the future. Don't rely on it yet.

export class ClientExtension implements HawkiAppExtension {
    private currentConnection: Connection | null = null;
    private resourceSchemas: HawkiAppExtensions['resourceSchemas'] | null = null;

    public readonly uriBuilder: UriBuilder = new UriBuilder(window.location.origin);
    public readonly client: HawkiClient;

    constructor() {
        const getConnection = () => this.getConnection();
        this.client = {
            restApi: new RestApi(
                this.uriBuilder,
                createDefaultTransport(),
                () => getConnection(),
                (resourceType: string) => {
                    if (!this.resourceSchemas) {
                        throw new Error('Resource schemas have not been loaded yet');
                    }
                    return this.resourceSchemas.get(resourceType);
                }
            ),
            aiApi: new AiApi(),
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

    private async reloadConnection(): Promise<Connection> {
        this.currentConnection = await this.client.restApi.getResource('connections', 'hawki');
        return this.currentConnection;
    }

    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void {
        this.resourceSchemas = app.getOrFail('resourceSchemas');

        bootstrapper.onPreparationStage(async () => {
            this.currentConnection = await this.client.restApi.getResource('connections', 'hawki');
        });
    }

    public provideProperties(): Record<string, any> {
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
            reloadConnection(): Promise<Connection> {
                return extension.reloadConnection();
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
            }
        };
    }
}
