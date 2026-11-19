<!--
  @component Breadcrumb trail for page header bars: a named navigation
  landmark holding the ordered list of BreadcrumbItem children, and the owner
  of everything trail-wide.

  Contract (after Meta's astryx Breadcrumbs):
  Owns — the <nav> landmark and its label (the `label` prop, falling back to
  the translated `ui.navigation.breadcrumb`), the single <ol>, the wrapping
  row layout, the distance to the floating nav trigger, and the separator
  glyph every item in its surface renders before itself (passed via context).
  Does not own — each crumb's element, glyph, accessible name, current-page
  state and menu (BreadcrumbItem); destination handling (named routes).

  Invariants:
  - FR1 one named <nav> around exactly one <ol>; the label names the landmark.
  - FR2 the list lays items out in a row that wraps; no markers, no inset.
  - FR3 every descendant item receives this trail's separator via context; an
    item rendered outside a trail throws.

  Deliberate deviations from astryx: crumbs are icon-only (labels are sr-only
  text plus a tooltip, owned by the item); destinations are named routes, not
  hrefs; the default chevron glyph lives with the item so trails stay
  snippet-free unless they override it.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {provideBreadcrumbTrail} from './breadcrumbContext';

    interface Props {
        /** The trail's BreadcrumbItem children. */
        children: Snippet;
        /** Trail-wide separator glyph, replacing the default chevron. */
        separator?: Snippet;
        /** Accessible name for the landmark; defaults to the translated
         *  `ui.navigation.breadcrumb`. */
        label?: string;
    }

    const {children, separator, label}: Props = $props();
    const {__} = useTranslator();

    // Context is set once at init; a trail does not swap its separator
    // mid-flight.
    // svelte-ignore state_referenced_locally
    provideBreadcrumbTrail({separator: separator ?? null});
</script>

<nav class="breadcrumb-trail" aria-label={label ?? __('ui.navigation.breadcrumb')}>
    <ol>
        {@render children()}
    </ol>
</nav>

<style>
    /* The bar's own padding leaves --space-2 behind the floating nav trigger;
       the same amount again keeps the two icon groups clearly apart. */
    .breadcrumb-trail {
        margin-left: var(--space-2);
    }

    .breadcrumb-trail ol {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-1);
        margin: 0;
        padding: 0;
        list-style: none;
    }
</style>
