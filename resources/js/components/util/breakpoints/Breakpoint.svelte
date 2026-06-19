<!--
  @component Conditionally renders content based on the current viewport width.

  Pass one or more named breakpoint snippets. The first matching snippet is
  rendered (CSS-file order by default). If no snippet matches, `children` is
  rendered as a fallback. Breakpoint names and queries are auto-generated from
  breakpoints.css by vitePluginBreakpoints.

  Basic switch (mobile vs. desktop):

    <Breakpoint>
      {#snippet bpSmallerThanMd()}Mobile layout{/snippet}
      {#snippet children()}Desktop layout{/snippet}
    </Breakpoint>

  Multiple named breakpoints:

    <Breakpoint>
      {#snippet bpXs()}Extra small{/snippet}
      {#snippet bpSm()}Small{/snippet}
      {#snippet bpMd()}Medium{/snippet}
      {#snippet children()}Large and above{/snippet}
    </Breakpoint>

  Custom priority order (first match wins):

    <Breakpoint order={['bpMdAndBigger', 'bpSmallerThanMd']}>
      {#snippet bpSmallerThanMd()}Narrow{/snippet}
      {#snippet bpMdAndBigger()}Wide{/snippet}
    </Breakpoint>

  Show all currently matching snippets simultaneously:

    <Breakpoint showAllMatching>
      {#snippet bpSmAndSmaller()}Small banner{/snippet}
      {#snippet bpMdAndSmaller()}Medium banner{/snippet}
    </Breakpoint>
-->
<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        breakpointsQueries,
        type Breakpoint,
        type BreakpointSnippetProps,
    } from './breakpoints.js';

    interface Props extends BreakpointSnippetProps {
        /**
         * Override the default evaluation order (CSS-file order).
         * When `showAllMatching` is false this is a priority list — the first
         * key that matches wins. When true it is the display order for all
         * matches. Every snippet key passed to this component must appear in
         * the array; omitting one is a dev-time error.
         */
        order?: Breakpoint[];
        /**
         * When true every matching breakpoint snippet is rendered (in `order`
         * / CSS-file order). When false (default) only the first match is
         * rendered. In both cases `children` is rendered only when nothing
         * else matches.
         */
        showAllMatching?: boolean;
    }

    const props: Props = $props();

    $effect(() => {
        const { order } = props;
        if (!order) return;
        const provided = (Object.keys(breakpointsQueries) as Breakpoint[]).filter(
            k => (props as Record<string, unknown>)[k] !== undefined,
        );
        const missing = provided.filter(k => !order.includes(k));
        if (missing.length > 0) {
            console.error(
                `[Breakpoint] These snippets are not listed in \`order\` and will be ignored: ${missing.join(', ')}`,
            );
        }
    });

    const supported = typeof window !== 'undefined' && typeof window.matchMedia === 'function';

    let matchStates = $state<Record<string, boolean>>(
        supported
            ? Object.fromEntries(
                  Object.entries(breakpointsQueries).map(([k, q]) => [k, window.matchMedia(q).matches]),
              )
            : {},
    );

    $effect(() => {
        if (!supported) return;
        const cleanups = (Object.entries(breakpointsQueries) as [string, string][]).map(
            ([key, query]) => {
                const mql = window.matchMedia(query);
                const onChange = (e: MediaQueryListEvent) => {
                    matchStates[key] = e.matches;
                };
                mql.addEventListener('change', onChange);
                return () => mql.removeEventListener('change', onChange);
            },
        );
        return () => cleanups.forEach(fn => fn());
    });

    const toRender = $derived.by((): Snippet[] => {
        const { order, showAllMatching = false } = props;
        const sequence = order ?? (Object.keys(breakpointsQueries) as Breakpoint[]);

        const matching = sequence.filter(
            k =>
                (props as Record<string, unknown>)[k] !== undefined &&
                matchStates[k],
        );

        if (matching.length > 0) {
            const keys = showAllMatching ? matching : [matching[0]];
            return keys.map(k => (props as Record<string, Snippet>)[k]);
        }

        return props.children ? [props.children] : [];
    });
</script>

{#each toRender as snippet (snippet)}
    {@render snippet()}
{/each}
