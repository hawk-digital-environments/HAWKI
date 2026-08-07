import {useApp} from '$lib/app/hooks/useApp.svelte.js';

export function useConnection() {
    const app = useApp();
    return app.connection;
}

export function useAuthenticatedConnection() {
    const app = useApp();

    try {
        return app.authenticatedConnection;
    } catch (error) {
        return null;
    }
}

export function useConnectionWithUserInfo() {
    const app = useApp();

    try {
        return app.connectionWithUserInfo;
    } catch (error) {
        return null;
    }
}
