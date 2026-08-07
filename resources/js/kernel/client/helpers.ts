import {Connection, type InternalAuthenticatedConnection, type InternalRegisteringUserConnection} from '$lib/app/schemas/resources/connections.schema';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * Returns the current HAWKI connection information. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet.
 * @deprecated Use {@link useConnection()} instead, as it is more explicit and avoids the need for a global function.
 */
export function getConnection(): Connection {
    return getHawkiApp().connection;
}

/**
 * Returns the current HAWKI connection information, but only if the client is authenticated. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet or if the client is not authenticated.
 * @deprecated Use {@link useAuthenticatedConnection()} instead, as it is more explicit and avoids the need for a global function.
 */
export function getAuthenticatedConnection(): InternalAuthenticatedConnection {
    return getHawkiApp().authenticatedConnection;
}

/**
 * Returns the current HAWKI connection information, but only if the client is authenticated or in the process of registering a new user account. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet or if the client is not authenticated or registering a new user.
 * @deprecated Use {@link useConnectionWithUserInfo()} instead, as it is more explicit and avoids the need for a global function.
 */
export function getConnectionWithUserInfo(): InternalAuthenticatedConnection | InternalRegisteringUserConnection {
    return getHawkiApp().connectionWithUserInfo;
}
