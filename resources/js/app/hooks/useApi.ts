import type {RestApi} from '$lib/kernel/api/RestApi.js';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';

export function useRestApi(): RestApi {
    const app = useApp();
    return app.restApi;
}

export function useLinkPreviewApi(): LinkPreviewApi {
    const app = useApp();
    return app.linkPreviewApi;
}
