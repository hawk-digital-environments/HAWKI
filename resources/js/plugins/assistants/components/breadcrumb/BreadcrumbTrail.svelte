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
    import type {OverlayContrastMode} from '$lib/utils/color.js';
    import {provideBreadcrumbTrail} from './breadcrumbContext';

    interface Props {
        /** The trail's BreadcrumbItem children. */
        children: Snippet;
        /** Trail-wide separator glyph, replacing the default chevron. */
        separator?: Snippet;
        /** Accessible name for the landmark; defaults to the translated
         *  `ui.navigation.breadcrumb`. */
        label?: string;
        /** Contrast mode of the cover the trail floats over (the assistant
         *  detail's bar): the crumbs switch from bare muted icons to chips
         *  in the mode's shade. Null/undefined keeps the plain look. */
        mode?: OverlayContrastMode | null;
    }

    const {children, separator, label, mode}: Props = $props();
    const {__} = useTranslator();

    // Context is set once at init; a trail does not swap its separator
    // mid-flight.
    // svelte-ignore state_referenced_locally
    provideBreadcrumbTrail({separator: separator ?? null});
</script>

<nav class="breadcrumb-trail" data-mode={mode ?? undefined} aria-label={label ?? __('ui.navigation.breadcrumb')}>
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

    /* Chips over a cover: the contrast mode picks the shade (see
       utils/color) and the whole trail becomes one translucent group pill in
       it — the cover-adaptive backdrop the detail's own top-bar buttons use,
       one shared shape instead of one per icon. Plain trails set none of
       these, so their crumbs keep the bare muted icons. The pill also
       re-enables pointer-events for itself: an over-cover bar disables them
       (its empty areas pass clicks through to the cover), and a snippet's
       roots cannot be re-enabled from the bar's own stylesheet. */
    .breadcrumb-trail[data-mode='dark'] {
        --crumb-fg: oklch(100% 0 0);
    }

    .breadcrumb-trail[data-mode='light'] {
        --crumb-fg: oklch(24% 0 0);
    }

    .breadcrumb-trail[data-mode] {
        --crumb-separator-color: color-mix(in oklab, var(--crumb-fg) 70%, transparent);
        pointer-events: auto;
    }

    .breadcrumb-trail[data-mode] ol {
        background: color-mix(in oklch, var(--crumb-fg) 14%, transparent);
        border-radius: var(--corner-full);
        padding-inline: var(--space-2);
        min-height: var(--nav-row-h);
    }
</style>
