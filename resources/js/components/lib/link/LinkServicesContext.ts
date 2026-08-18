import {createContext} from 'svelte';

/**
 * Rich link-preview metadata for a URL (title, description, image, favicon,
 * domain). Package-owned shape, modeled on exactly what `UrlPreviewTooltip`
 * renders — not on any host API response type, so this package never depends
 * on a host's schema/validation layer.
 */
export interface LinkPreviewMetadata {
    url: string;
    title: string | null;
    description: string | null;
    image: string | null;
    favicon: string | null;
    domain: string | null;
}

/**
 * Host-injected services `Link`/`TextLink` and `UrlPreviewTooltip` use to
 * reach outside this package. Both members are optional — a host that
 * injects neither still gets fully-functional links, just without a favicon
 * or a rich preview tooltip. That is the same behaviour these components
 * already have for same-origin/local links, not a new degraded mode.
 */
export interface LinkServices {
    /**
     * Resolves the favicon image URL for an external `url`. Return `null`
     * (or omit this member entirely) to render no favicon.
     */
    faviconUrl?(url: string): string | null;

    /**
     * Fetches rich preview metadata for `url`. Used by `UrlPreviewTooltip`;
     * omit this member to make the tooltip degrade to rendering nothing.
     */
    fetchPreview?(url: string): Promise<LinkPreviewMetadata>;
}

const [get, set] = createContext<LinkServices>();

/**
 * Returned by {@link useLinkServices} when no ancestor called
 * {@link provideLinkServices} — an empty object, so both members read as
 * `undefined` and every call site's existing "service absent" branch runs.
 */
const standaloneLinkServices: LinkServices = {};

/**
 * Publishes {@link LinkServices} to the component subtree. Call once, during
 * component initialization (a host app's root layout / every Svelte mount
 * root).
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {provideLinkServices} from '@hawk-hhg/hawki-svelte-components';
 *
 *     provideLinkServices({
 *         faviconUrl: (url) => app.uriBuilder.linkPreviewFaviconUri(url),
 *         fetchPreview: (url) => app.linkPreviewApi.getLinkPreviewMetadata(url)
 *     });
 * </script>
 * ```
 */
export function provideLinkServices(services: LinkServices): void {
    set(services);
}

/**
 * Reads the {@link LinkServices} published by an ancestor
 * {@link provideLinkServices} call. Falls back to an object with neither
 * member set when no host provided one, so `Link` and `UrlPreviewTooltip`
 * degrade gracefully instead of throwing.
 */
export function useLinkServices(): LinkServices {
    try {
        return get();
    } catch {
        return standaloneLinkServices;
    }
}
