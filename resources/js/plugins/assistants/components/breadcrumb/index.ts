/**
 * Breadcrumb component family (contract after Meta's astryx): the trail owns
 * the landmark, list, label and separator; each item owns its crumb. Compose
 * `BreadcrumbItem` children inside a `BreadcrumbTrail`; menu crumbs compose
 * the shared `DropdownMenu` item components verbatim.
 */
export {default as BreadcrumbTrail} from './BreadcrumbTrail.svelte';
export {default as BreadcrumbItem} from './BreadcrumbItem.svelte';
export {useBreadcrumbTrail} from './breadcrumbContext';
export type {BreadcrumbTrailContext} from './breadcrumbContext';
