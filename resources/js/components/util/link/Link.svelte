<!--
  @component Accessible anchor element with safety guardrails and a disabled
  state. Prefer this over a bare `<a>` whenever you need any of:
  - Automatic `rel="noopener noreferrer"` on external links (`target="_blank"`)
    to prevent tabnabbing.
  - A `disabled` state that keeps the element in the DOM (and in the tab order)
    while blocking navigation — plain `<a>` has no native disabled behaviour.

  Basic navigation:

    <Link href="/settings">Settings</Link>

  External link — rel is set automatically, no extra props needed:

    <Link href="https://example.com" target="_blank">Open docs</Link>

  Disabled link — greyed out (opacity 0.5), clicks are swallowed:

    <Link href="/delete" disabled>Delete</Link>

  Custom click handler (stays on the page, no navigation):

    <Link href="" onclick={() => openModal()}>Open modal</Link>

  For external links (different origin) a favicon snippet is passed to the
  `children` snippet, loaded through the backend proxy. Layout consumers decide
  where (and whether) to render it:

    <Link href="https://example.com" target="_blank">
        {#snippet children({favicon})}
            <span class="header">{@render favicon()} example.com</span>
        {/snippet}
    </Link>

  For plain text links with the favicon prepended automatically, use
  `TextLink.svelte` instead.

  Router-driven links get an `active` class and `aria-current="page"` while they
  point at the page currently shown. Use `activeMatch="prefix"` for a section
  link that should stay lit on nested pages:

    <Link href="/admin" activeMatch="prefix">Admin</Link>

  The `active` class carries no styling of its own — set `--link-active-color`
  or `--link-active-font-weight` on an ancestor to give it a look.

  All standard `<a>` attributes are forwarded via rest-props.
-->
<script lang="ts">
    import type {HTMLAnchorAttributes, MouseEventHandler} from 'svelte/elements';
    import * as svelte from 'svelte';
    import {mergeProps} from 'bits-ui';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import type {RouteParams} from 'universal-router';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';

    // widen so Props can redefine safely
    interface NonConflictingProps extends HTMLAnchorAttributes {
        href?: any;
        children?: any;
    }

    interface Props extends NonConflictingProps {
        /**
         * The URL to navigate to. When empty or when `disabled` is true the
         * rendered `href` becomes `javascript:void(0)` so the element remains
         * keyboard-focusable without causing navigation.
         */
        href?: string | { name: string; params?: RouteParams };

        router?: string;

        /**
         * Standard anchor `target`. Omit to use the browser default.
         *
         * **Do not use `_self`** — in SvelteKit `target="_self"` triggers a
         * full-page reload instead of client-side navigation.
         *
         * @see https://github.com/sveltejs/sapper/issues/265
         */
        target?: string;

        /**
         * Overrides the auto-computed `rel` attribute. When omitted and
         * `target="_blank"`, defaults to `"noopener noreferrer"` to prevent
         * tabnabbing. Pass an explicit value (e.g. `"noreferrer"`) to override.
         */
        rel?: string;

        /** Click handler. When `disabled` is true this is replaced by a
         * handler that calls `event.preventDefault()`, so navigation is always
         * blocked regardless of what the consumer passes. */
        onclick?: MouseEventHandler<HTMLAnchorElement>;

        /**
         * Link content. Receives a `favicon` snippet that renders the target
         * site's favicon (or nothing for same-origin/non-http links, or when
         * the icon failed to load). Render it wherever it fits your layout —
         * or ignore it for icon-less links.
         */
        children?: svelte.Snippet<[{ favicon: svelte.Snippet }]>;

        /**
         * When true: blocks navigation, sets `href` to `javascript:void(0)`,
         * and adds the `disabled` CSS class (opacity 0.5, pointer-events none).
         * The element stays in the DOM and remains keyboard-focusable.
         */
        disabled?: boolean;

        /**
         * When the link counts as "active", i.e. gets the `active` class and
         * `aria-current="page"`. Only ever true for router-driven links.
         *
         * - `exact` (default) — only while the current path *is* the target.
         * - `prefix` — also while a path below the target is open, so a section
         *   link stays lit on its child pages. Ignored for the application root,
         *   which prefixes everything.
         * - `never` — opts out of active tracking entirely.
         */
        activeMatch?: 'exact' | 'prefix' | 'never';

        /**
         * Overrides the computed active state. Use for targets the router
         * cannot judge on its own — e.g. a section whose routes do not share a
         * common path prefix, via `useRouter().isRouteActive('section')`.
         */
        active?: boolean;
    }

    const {
        href: givenHref = '',
        router: routerName,
        target = '',
        rel: relRaw = '',
        onclick: onclickRaw,
        children,
        disabled,
        activeMatch = 'exact',
        active: activeOverride,
        ...restProps
    }: Props = $props();

    let faviconFailed = $state(false);

    const app = useApp();

    const hrefIsRoute = $derived.by(() => {
        if (!givenHref) {
            return false;
        }
        return (typeof givenHref === 'object' && givenHref.name)
            || (typeof givenHref === 'string' && givenHref.startsWith('@'));
    });
    const hrefIsLocal = $derived.by(() => {
        if (hrefIsRoute) {
            return true;
        }
        if (!givenHref || typeof givenHref !== 'string') {
            return false;
        }
        try {
            const parsed = new URL(givenHref, window.location.origin);
            return parsed.origin === window.location.origin;
        } catch {
            return givenHref.startsWith('/') || givenHref.startsWith('#') || givenHref.startsWith('?');
        }
    });
    const router = $derived.by(() => {
        // If the href is not a route, nor a local link, we don't need a router
        if (!hrefIsLocal) {
            return null;
        }
        try {
            return useRouter(routerName);
        } catch {
            throw new Error(`Router "${routerName}" not found`);
        }
    });
    const href = $derived.by(() => {
        if (!router) {
            if (!givenHref || typeof givenHref !== 'string' || disabled) {
                return 'javascript:void(0)';
            }
        }

        if (!hrefIsRoute) {
            // A literal path that the router owns must be run through
            // `getPath` so the router's `basePath` is applied — otherwise a
            // href like `/settings` under a `/new` base is rendered and
            // navigated outside the SPA, producing a 404. Non-routable local
            // hrefs (`#anchor`, `?q=1`, relative URLs) are returned verbatim
            // so the browser handles them natively.
            if (router && typeof givenHref === 'string' && router.canHandlePath(givenHref)) {
                return router.getPath(givenHref);
            }
            return givenHref as string;
        }

        function getPathFromRouter(hrefRaw: any) {
            try {
                return router!.getPath(hrefRaw.name, hrefRaw.params);
            } catch {
                throw new Error(`Failed to resolve named route "${hrefRaw.name}" with params ${JSON.stringify(hrefRaw.params)}`);
            }
        }

        if (typeof givenHref === 'object' && givenHref.name) {
            return getPathFromRouter(givenHref);
        }
        if (typeof givenHref === 'string' && givenHref.startsWith('@')) {
            return getPathFromRouter({name: givenHref.slice(1), router: 'app'});
        }
        throw new Error(`Invalid href prop: ${JSON.stringify(givenHref)}`);
    });

    const isActive = $derived.by(() => {
        if (activeOverride !== undefined) {
            return activeOverride;
        }
        if (!router || disabled || activeMatch === 'never') {
            return false;
        }
        // Hash anchors, query-only links, relative URLs and similar local
        // but non-routable hrefs never describe a route, so they can never be
        // the active one — see `RoutingStrategy.canHandlePath`.
        if (!router.canHandlePath(href)) {
            return false;
        }
        return router.isActive(href, {startsWith: activeMatch === 'prefix'});
    });

    const faviconUrl = $derived.by(() => {
        if (!givenHref || faviconFailed || hrefIsLocal) {
            return null;
        }
        try {
            const parsed = new URL(href, window.location.origin);
            if (!/^https?:$/.test(parsed.protocol) || parsed.origin === window.location.origin) {
                return null;
            }
            return app.uriBuilder.linkPreviewFaviconUri(href);
        } catch {
            return null;
        }
    });

    const rel = $derived.by(() => {
        if (relRaw) {
            return relRaw;
        }
        if (target === '_blank') {
            return 'noopener noreferrer';
        }
        return '';
    });

    const onclick = $derived.by(() => {
        if (disabled) {
            return (event: MouseEvent) => {
                event.preventDefault();
            };
        }
        if (router && router.canHandlePath(href)) {
            return (event: MouseEvent) => {
                onclickRaw?.(event as any);
                if (event.defaultPrevented) {
                    return;
                }
                event.preventDefault();
                router.goTo(href);
            };
        }
        return onclickRaw;
    });

    const dynamicProps = $derived.by(() => {
        const props: Record<string, any> = {};
        if (target) {
            props.target = target;
        }
        if (rel) {
            props.rel = rel;
        }
        if (onclick) {
            props.onclick = onclick;
        }
        if (isActive) {
            props['aria-current'] = 'page';
        }
        return props;
    });
</script>

{#snippet favicon()}
    {#if faviconUrl}
        <img
            class="favicon"
            src={faviconUrl}
            alt=""
            aria-hidden="true"
            loading="lazy"
            onerror={() => faviconFailed = true}
        />
    {/if}
{/snippet}

<a {...mergeProps(
    {
        href,
        class: {
            disabled: disabled,
            active: isActive
        }
    },
    dynamicProps,
    restProps
)}>
    {@render children?.({favicon})}
</a>

<style>
    .disabled {
        pointer-events: none;
        opacity: 0.5;
    }

    /* Styling hook only — inherits unless a consumer sets the tokens, so the
       active state never changes a link's look without being asked to. */
    .active {
        color: var(--link-active-color, inherit);
        font-weight: var(--link-active-font-weight, inherit);
    }

    .favicon {
        display: inline-block;
        width: var(--favicon-size, 1em);
        height: var(--favicon-size, 1em);
        margin-inline-end: var(--favicon-gap, var(--space-1));
        vertical-align: -0.125em;
        border-radius: var(--corner-sm);
    }
</style>
