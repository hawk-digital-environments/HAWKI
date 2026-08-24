import {useApp} from '$lib/app/hooks/useApp.svelte.js';

// @todo the underlying ClientExtension/connection concept is not fully settled and WILL be refactored/changed in the future. Don't rely on it too heavily yet.

/**
 * Hook that gives components access to the current connection resource
 * (`app.connection`) — the client's session/handshake info fetched once from
 * the `'connections'` resource (id `'hawki'`) during the bootstrapper's
 * `preparation` stage.
 *
 * Returns a reactive box: read `.current` inside a `$derived` or template so
 * the component tracks the underlying state and sees the new connection after
 * `refreshConnection()` swaps it (e.g. when the type changes from
 * `'internal_registering_user'` to `'internal_authenticated'`).
 *
 * The boxed `Connection` is a discriminated union on its `type` field:
 * `'internal'` (anonymous/unauthenticated), `'internal_authenticated'`
 * (logged-in user, includes `userinfo`), or `'internal_registering_user'`
 * (mid-registration, includes partial `userinfo`). Narrow on `type` yourself
 * if you need to branch on connection state — or use
 * {@link useAuthenticatedConnection}/{@link useConnectionWithUserInfo} if you
 * only care about the authenticated/user-info cases.
 *
 * Reading `.current` throws (does not catch) if the connection has not been
 * loaded yet — this should not normally happen in component code, since
 * components only render after bootstrap has passed the `preparation` stage.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useConnection} from '$lib/app/hooks/useConnection.svelte.js';
 *
 *     const connectionBox = useConnection();
 *     const connection = $derived(connectionBox.current);
 * </script>
 *
 * <p>Backend locale: {connection.locale}</p>
 * ```
 */
export function useConnection() {
    const app = useApp();
    return {
        get current() {
            return app.connection;
        }
    };
}

/**
 * Hook variant of {@link useConnection} for the "the user must be logged in"
 * case: the box's `.current` is the connection narrowed to
 * `type === 'internal_authenticated'` (with its `userinfo`), or `null` if the
 * current connection is not authenticated (or not loaded yet) — unlike
 * `app.authenticatedConnection`, which throws in both cases, this hook
 * swallows the error so templates can simply check for `null` instead of
 * handling exceptions.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useAuthenticatedConnection} from '$lib/app/hooks/useConnection.svelte.js';
 *
 *     const connectionBox = useAuthenticatedConnection();
 *     const connection = $derived(connectionBox.current);
 * </script>
 *
 * {#if connection}
 *     <p>Logged in as {connection.userinfo.name}</p>
 * {/if}
 * ```
 */
export function useAuthenticatedConnection() {
    const app = useApp();

    return {
        get current() {
            try {
                return app.authenticatedConnection;
            } catch (error) {
                return null;
            }
        }
    };
}

/**
 * Hook variant of {@link useConnection} for "any connection that carries user
 * info": the box's `.current` is the connection narrowed to
 * `type === 'internal_authenticated'` or `type === 'internal_registering_user'`
 * (both have a `userinfo` field, though the registering-user variant's
 * `userinfo` may be partially filled), or `null` otherwise (including if the
 * connection has not been loaded yet).
 *
 * Use this instead of {@link useAuthenticatedConnection} when a component
 * (e.g. a profile display during signup) needs to show user info regardless
 * of whether registration has fully completed.
 */
export function useConnectionWithUserInfo() {
    const app = useApp();

    return {
        get current() {
            try {
                return app.connectionWithUserInfo;
            } catch (error) {
                return null;
            }
        }
    };
}
