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

  All standard `<a>` attributes are forwarded via rest-props.
-->
<script lang="ts">
    import type {HTMLAnchorAttributes, MouseEventHandler} from 'svelte/elements';
    import * as svelte from 'svelte';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAnchorAttributes {
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

        /** Link content. */
        children?: svelte.Snippet;

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
    {@render children?.()}
</a>

<style>
    .disabled {
        pointer-events: none;
        opacity: 0.5;
    }
</style>
