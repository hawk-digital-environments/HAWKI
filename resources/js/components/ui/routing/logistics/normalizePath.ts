/**
 * Normalizes a router base path to the form `universal-router` expects:
 * either an empty string or `/segment` without a trailing slash.
 *
 * Unlike {@link normalizePath}, "no base" is `''` — not `'/'`. UniversalRouter
 * strips the base by raw character count (`pathname.substr(baseUrl.length)`),
 * so a base of `'/'` silently eats the leading slash of every path and makes
 * all non-root routes unresolvable.
 */
export function normalizeBasePath(basePath: string | null | undefined): string {
    if (!basePath) {
        return '';
    }
    const normalized = normalizePath(basePath);
    return normalized === '/' ? '' : normalized;
}

/**
 * Canonicalizes a path for comparison and storage: trims whitespace, adds a
 * leading slash if missing, and strips a trailing slash unless the path is
 * just `/`. Nullish or blank input normalizes to `/` (the root) — unlike
 * {@link normalizeBasePath}, for which "no base" means `''`, not `/`.
 */
export function normalizePath(path: string | null | undefined): string {

    if (!path || typeof path === 'string' && path.trim() === '') {
        return '/';
    }

    path = path.trim();

    // Ensure the path starts with a leading slash
    if (!path.startsWith('/')) {
        path = '/' + path;
    }

    // Ensure path does not end with a trailing slash (unless it's just "/")
    if (path.length > 1 && path.endsWith('/')) {
        path = path.slice(0, -1);
    }

    return path;
}

/** Joins `basePath` and `relativePath` with exactly one `/` between them, e.g. `mergePaths('/admin', '/users')` and `mergePaths('/admin', 'users')` both yield `/admin/users`. An empty `relativePath` yields `basePath` with its trailing slash removed. */
export function mergePaths(basePath: string, relativePath: string): string {
    if (!basePath.endsWith('/')) {
        basePath += '/';
    }
    if (relativePath.startsWith('/')) {
        relativePath = relativePath.substring(1);
    }
    if (relativePath.length === 0) {
        return basePath.slice(0, -1); // Remove trailing slash if relativePath is empty
    }
    return basePath + relativePath;
}
