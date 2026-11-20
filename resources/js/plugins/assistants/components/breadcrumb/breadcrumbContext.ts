import type {Snippet} from 'svelte';
import {createStableContext} from '$lib/utils/stableContext';

/** What every descendant crumb receives from its trail. */
export interface BreadcrumbTrailContext {
    /** Separator glyph each item renders before itself; `null` means the
     *  item-side default chevron. */
    separator: Snippet | null;
}

/**
 * The trail→item channel: `BreadcrumbTrail` provides it, `BreadcrumbItem`
 * consumes it — an item rendered outside a trail throws.
 */
export const [useBreadcrumbTrail, provideBreadcrumbTrail] =
    createStableContext<BreadcrumbTrailContext>('hawki.assistants.breadcrumb-trail');
