import {getResourceFromApi} from '$lib/data/api/api.js';
import {Connection, type InternalAuthenticatedConnection, type InternalRegisteringUserConnection} from '$lib/schemas/resources/connections.schema.js';


let currentConnection: Connection | null = null;

/**
 * Loads the current HAWKI connection information from the API and stores it in memory.
 * This should be called once when the application starts, before any components need to access the connection info.
 */
export async function loadConnection(): Promise<void> {
    currentConnection = await getResourceFromApi('connections', 'hawki');
}

/**
 * Returns the current HAWKI connection information. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet.
 */
export function getConnection(): Connection {
    if (!currentConnection) {
        throw new Error('Connection not loaded. Please call loadConnection() before accessing the connection.');
    }
    return currentConnection;
}

/**
 * Returns the current HAWKI connection information, but only if the client is in the process of registering a new user account. This includes the API version and registering user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet or if the client is not in the process of registering a new user.
 */
export function getRegisteringUserConnection(): InternalRegisteringUserConnection {
    const connection = getConnection();
    if (connection.type !== 'internal_registering_user') {
        throw new Error('Current connection is not a registering user connection');
    }
    return connection;
}

/**
 * Returns the current HAWKI connection information, but only if the client is authenticated. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet or if the client is not authenticated.
 */
export function getAuthenticatedConnection(): InternalAuthenticatedConnection {
    const connection = getConnection();
    if (connection.type !== 'internal_authenticated') {
        throw new Error('Current connection is not authenticated');
    }
    return connection;
}

/**
 * Returns the current HAWKI connection information, but only if the client is authenticated or in the process of registering a new user account. This includes the API version and user info.
 * The connection must be loaded first by calling `loadConnection()`.
 *
 * @throws Error if the connection has not been loaded yet or if the client is not authenticated or registering a new user.
 */
export function getConnectionWithUserInfo(): InternalAuthenticatedConnection | InternalRegisteringUserConnection {
    const connection = getConnection();
    if (connection.type === 'internal_authenticated' || connection.type === 'internal_registering_user') {
        return connection;
    }
    throw new Error('Current connection does not contain user info');
}
