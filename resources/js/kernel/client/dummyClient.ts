import {RestApi} from '$lib/kernel/api/RestApi.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';
import type {AiApi} from '$lib/kernel/ai/AiApi.js';

/* ============================
 * DUMMY IMPLEMENTATION
 * ============================
 *
 * Long term we want to have a shared client library with (https://github.com/hawk-digital-environments/hawki-client).
 * This means the built-in HAWKI backend will use the same client library as the external clients.
 * Until the main migration effort towards v3.0.0 is done we will use this dummy implementation to get the frontend running.
 * Don't rely on ANYTHING in this dummy implementation, or be prepared for breaking changes in the future.
 */

/**
 * This is a placeholder interface for the HAWKI client.
 * The current priority is to get the actual frontend running on svelte,
 * but in the future we want to migrate on a shared client library, which was
 * started for 2.3.0 in https://github.com/hawk-digital-environments/hawk-auth-client
 * but was not finished yet.
 * @todo: Implement the shared client library and migrate the frontend to use it.
 */
export interface HawkiClient {
    restApi: RestApi;
    aiApi: AiApi;
    connection: Connection;
}
