import type {RestApi} from '$lib/kernel/api/RestApi.js';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {LinkPreviewApi} from '$lib/kernel/api/LinkPreviewApi.js';
import type {AiApi} from '$lib/kernel/ai/AiApi.js';

/**
 * Hook that gives components access to the app's low-level typed JSON:API
 * REST client (`app.restApi`).
 *
 * Use this whenever a component needs to talk to the HAWKI backend directly
 * for CRUD resources (`getResource`, `getResourceCollection`) or RPC-style,
 * non-CRUD endpoints (`getFromResourceAction`, `postToResourceAction`).
 * Responses are automatically decoded from the JSON:API envelope and
 * validated against the resource's registered Zod schema (see
 * `HawkiResourceSchemas` in `kernel/extendableTypes.ts`), so call sites get
 * fully-typed, validated data without manual casting.
 *
 * For most feature code, prefer building a small dedicated service/store on
 * top of `RestApi` rather than calling it ad-hoc from many components — this
 * hook is the low-level building block those services are built from.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useRestApi} from '$lib/app/hooks/useApi.js';
 *
 *     const restApi = useRestApi();
 *     const migrations = await restApi.getResourceCollection('migrations');
 * </script>
 * ```
 */
export function useRestApi(): RestApi {
    const app = useApp();
    return app.restApi;
}

/** Returns the internal streaming AI client (`app.aiApi`). */
export function useAiApi(): AiApi {
    return useApp().aiApi;
}

/**
 * Hook that gives components access to the app's link-preview API/cache
 * (`app.linkPreviewApi`).
 *
 * Use this specifically for rendering rich link previews (title, description,
 * image, favicon, domain) for a URL — e.g. inside a tooltip when hovering a
 * link. `getLinkPreviewMetadata(url)` caches its result per URL for the page
 * lifetime, so calling it repeatedly for the same URL is cheap.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useLinkPreviewApi} from '$lib/app/hooks/useApi.js';
 *
 *     const api = useLinkPreviewApi();
 *     const metadata = await api.getLinkPreviewMetadata(url);
 * </script>
 * ```
 */
export function useLinkPreviewApi(): LinkPreviewApi {
    const app = useApp();
    return app.linkPreviewApi;
}
