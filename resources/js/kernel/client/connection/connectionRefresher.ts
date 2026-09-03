import type {ConnectionHandle} from '$lib/kernel/client/connection/ConnectionHandle.svelte.js';

/** How often the connection is re-validated against the backend while the app is open. */
export const AUTH_CHECK_INTERVAL_MS = 15 * 60 * 1000;

/**
 * Keeps the connection fresh for the lifetime of the app: re-validates on a
 * fixed interval and whenever the tab regains focus. Concurrent triggers
 * (e.g. a focus event firing mid-interval) are coalesced via
 * `isRefreshPending` so only one request is in flight at a time.
 *
 * Returns a teardown that removes the listeners and clears the timer — unused
 * today but kept so a future app-wide "destroy" can wire it up.
 */
export function registerConnectionRefresher(
    handle: ConnectionHandle,
    intervalMs: number = AUTH_CHECK_INTERVAL_MS
) {

    let isRefreshPending = false;

    async function refresh() {
        if (isRefreshPending) {
            return;
        }
        isRefreshPending = true;

        try {
            await handle.refreshConnection();
        } catch (error) {
            console.warn('AuthChecker: refreshing the connection failed', error);
        } finally {
            isRefreshPending = false;
        }
    }

    const onRefocus = () => {
        if (document.visibilityState === 'visible') {
            void refresh();
        }
    };

    const interval = window.setInterval(() => void refresh(), intervalMs);
    window.addEventListener('focus', onRefocus);
    document.addEventListener('visibilitychange', onRefocus);

    return () => {
        window.clearInterval(interval);
        window.removeEventListener('focus', onRefocus);
        document.removeEventListener('visibilitychange', onRefocus);
    };
}
