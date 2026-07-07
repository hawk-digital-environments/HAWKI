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

  All standard `<a>` attributes are forwarded via rest-props.
-->
<script lang="ts">
    import type {HTMLAnchorAttributes, MouseEventHandler} from 'svelte/elements';
    import * as svelte from 'svelte';
    import {mergeProps} from 'bits-ui';
    import {buildLinkPreviewFaviconUrl} from '$lib/data/api/linkPreview.js';

    interface NonConflictingProps extends HTMLAnchorAttributes {
        children?: any; // widen so Props can redefine safely
    }

    interface Props extends NonConflictingProps {
        /**
         * The URL to navigate to. When empty or when `disabled` is true the
         * rendered `href` becomes `javascript:void(0)` so the element remains
         * keyboard-focusable without causing navigation.
         */
        href?: string;

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
    }

    const {
        href: hrefRaw = '',
        target = '',
        rel: relRaw = '',
        onclick: onclickRaw,
        children,
        disabled,
        ...restProps
    }: Props = $props();

    let faviconFailed = $state(false);

    const faviconUrl = $derived.by(() => {
        if (!hrefRaw || faviconFailed) {
            return null;
        }
        try {
            const parsed = new URL(hrefRaw, window.location.origin);
            if (!/^https?:$/.test(parsed.protocol) || parsed.origin === window.location.origin) {
                return null;
            }
            return buildLinkPreviewFaviconUrl(hrefRaw);
        } catch {
            return null;
        }
    });

    const href = $derived.by(() => {
        if (!hrefRaw || disabled) {
            return 'javascript:void(0)';
        }
        return hrefRaw;
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
            disabled: disabled
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

    .favicon {
        display: inline-block;
        width: var(--favicon-size, 1em);
        height: var(--favicon-size, 1em);
        margin-inline-end: var(--favicon-gap, var(--space-1));
        vertical-align: -0.125em;
        border-radius: var(--corner-sm);
    }
</style>
