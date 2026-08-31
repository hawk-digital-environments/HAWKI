import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';

// @todo the underlying ClientExtension/connection concept is not fully settled and WILL be refactored/changed in the future. Don't rely on it too heavily yet.

/**
 * Hook that gives components access to the current connection resource
 * (`app.connection`) — the client's session/handshake info fetched once from
 * the `'connections'` resource (id `'hawki'`) during the bootstrapper's
 * `preparation` stage.
 *
 * The returned object is reactive and keeps its identity for the lifetime of
 * the page: `refreshConnection()` updates it in place rather than replacing
 * it, so a plain `const connection = useConnection()` stays live and sees the
 * new session (e.g. when the type changes from `'internal_registering_user'`
 * to `'internal_authenticated'`). You do not need `$derived` for that — only
 * for values you compute *from* it.
 *
 * The `Connection` is a discriminated union on its `type` field: `'internal'`
 * (anonymous/unauthenticated), `'internal_authenticated'` (logged-in user,
 * includes `userinfo`), or `'internal_registering_user'` (mid-registration,
 * includes partial `userinfo`). Narrow with the `isAuthenticated` /
 * `hasUserInfo` flags rather than comparing `type` by hand — both are real
 * discriminants, so TypeScript narrows on them:
 *
 * - `connection.isAuthenticated` → `'internal_authenticated'`
 * - `connection.hasUserInfo` → `'internal_authenticated' | 'internal_registering_user'`
 *
 * Calling this throws if the connection has not been loaded yet — this should
 * not normally happen in component code, since components only render after
 * bootstrap has passed the `preparation` stage.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useConnection} from '$lib/app/hooks/useConnection.svelte.js';
 *
 *     const connection = useConnection();
 *     const userName = $derived(connection.hasUserInfo ? connection.userinfo.name : null);
 * </script>
 *
 * <p>Backend locale: {connection.locale}</p>
 * {#if connection.isAuthenticated}
 *     <p>Logged in as {connection.userinfo.name}</p>
 * {/if}
 * ```
 */
export function useConnection(): Connection {
    return useApp().connection;
}
