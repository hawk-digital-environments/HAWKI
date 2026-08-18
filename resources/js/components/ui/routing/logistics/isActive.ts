/**
 * Decides whether a link target counts as "currently active" — the state a
 * navigation item uses to highlight itself.
 *
 * Two independent questions live here, because they have different answers:
 *
 * - {@link isPathActive} — "is this exact URL the one we are on?" Compares
 *   concrete paths, so two links to the same route with different params stay
 *   distinguishable (a conversation list highlights only the open conversation).
 * - {@link isRouteActive} — "is this route currently rendered, or an ancestor
 *   of what is rendered?" Ignores params entirely, which is what a section
 *   header wants but what a list item must never use.
 *
 * Neither function deals with query strings or fragments: paths in this router
 * never carry them.
 */
import type {Route, RouteContext} from 'universal-router';
import {normalizePath} from './normalizePath.js';

export interface IsPathActiveOptions {
    /**
     * When true the target also counts as active while a path *below* it is
     * open — `/admin` stays active on `/admin/users`.
     *
     * Matching is segment-aware, so `/admin` does not match `/administration`.
     */
    startsWith?: boolean;

    /**
     * The router's base path, used to recognise the application root. The root
     * is a prefix of every other path, so `startsWith` is ignored for it —
     * otherwise a "Home" link would light up on every route. Defaults to `/`.
     */
    rootPath?: string;
}

/**
 * Compares two concrete paths. Both sides are normalized first, so callers do
 * not have to care about trailing slashes.
 */
export function isPathActive(
    currentPath: string,
    targetPath: string,
    options: IsPathActiveOptions = {}
): boolean {
    const current = normalizePath(currentPath);
    const target = normalizePath(targetPath);

    if (current === target) {
        return true;
    }

    if (!options.startsWith) {
        return false;
    }

    // `normalizePath('')` yields '/', which is also the root when no base path is set.
    if (target === normalizePath(options.rootPath)) {
        return false;
    }

    // The trailing slash is what makes this segment-aware.
    return current.startsWith(target + '/');
}

/**
 * Walks the matched route up its `parent` chain — populated by
 * `universal-router` while matching — and reports whether `routeName` appears
 * anywhere along it.
 *
 * Middleware wrapper routes sit in that chain as unnamed, path-less entries
 * (see `buildMiddlewareStack`); they are skipped implicitly because they have
 * no name to match.
 *
 * Only routes that were given a `name` can be matched this way. Route groups
 * need `name` set in their {@link RouteGroupOptions} to participate.
 */
export function isRouteActive(
    context: RouteContext | null,
    routeName: string
): boolean {
    let route: Route | null | undefined = context?.route;

    while (route) {
        if (route.name === routeName) {
            return true;
        }
        route = route.parent;
    }

    return false;
}
