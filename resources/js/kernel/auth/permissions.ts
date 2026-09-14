import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';

export function can(connection: Connection | null, permission: string): boolean {
    return connection?.isAuthenticated === true && connection.userinfo.permissions.includes(permission);
}
