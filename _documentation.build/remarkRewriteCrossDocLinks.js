import {visit} from 'unist-util-visit';

/**
 * A remark plugin that rewrites cross-documentation-root links in markdown files.
 *
 * In the source markdown, authors write relative links across doc roots like:
 *   ../_documentation/3-architecture/10-dot Env.md#ANCHOR  (from _changelog)
 *   ../_changelog/2.4.0.md                                 (from _documentation)
 *
 * These links work on GitHub but break in Docusaurus because `_changelog` and `_documentation`
 * are separate docs plugin instances with different base paths.
 *
 * This plugin rewrites those links to absolute Docusaurus URLs by:
 *   1. Detecting `../_documentation/` or `../_changelog/` prefixes
 *   2. Removing the `.md` extension
 *   3. Stripping Docusaurus-style number prefixes (e.g. `3-` from dirs, `10-` from files)
 *   4. Prepending the target routeBasePath (`/` for docs, `/changelog` for changelog)
 *
 * The result is a valid absolute URL like `/architecture/dot%20Env#anchor`
 * or `/changelog/2.4.0`
 */
function remarkRewriteCrossDocLinks() {
    /**
     * Strips a Docusaurus-style number prefix from a single path segment.
     * E.g. "3-architecture" -> "architecture", "10-dot Env" -> "dot Env"
     * But preserves prefixes like "10.1-Model Config" (version-like, ignored by Docusaurus).
     */
    function stripNumberPrefix(segment) {
        // Docusaurus ignores prefixes that look like versions: \d+[-_.]\d+
        if (/^\d+[-_.]\d+/.test(segment)) {
            return segment;
        }
        // Strip pattern: leading digits followed by separator(s)
        return segment.replace(/^\d+\s*[-_.]+\s*/, '');
    }

    /**
     * Maps a `../<sourceDir>/` prefix to the corresponding Docusaurus routeBasePath.
     */
    const prefixToRouteBase = {
        '_documentation': '',    // routeBasePath: '/'
        '_changelog': 'changelog' // routeBasePath: 'changelog'
    };

    const prefixPattern = new RegExp(
        '^\\.\\.\/(' + Object.keys(prefixToRouteBase).join('|') + ')\/(.*)'
    );

    return (tree) => {
        visit(tree, 'link', (node) => {
            if (!node.url) return;

            const match = node.url.match(prefixPattern);
            if (!match) return;

            const sourceDir = match[1];
            const routeBase = prefixToRouteBase[sourceDir];
            let targetPath = match[2];

            // Separate anchor from path
            let anchor = '';
            const hashIndex = targetPath.indexOf('#');
            if (hashIndex !== -1) {
                anchor = targetPath.substring(hashIndex).toLowerCase();
                targetPath = targetPath.substring(0, hashIndex);
            }

            // Remove .md extension
            targetPath = targetPath.replace(/\.md$/, '');

            // Decode URL encoding (e.g. %20 -> space) so we can process segments
            targetPath = decodeURIComponent(targetPath);

            // Strip number prefixes from each path segment
            const segments = targetPath.split('/').map(stripNumberPrefix);

            // Re-encode spaces and rebuild path
            const rewrittenPath = '/' + [routeBase, ...segments]
                .filter(Boolean)
                .map(s => encodeURIComponent(s))
                .join('/');

            node.url = rewrittenPath + anchor;
        });
    };
}

export default remarkRewriteCrossDocLinks;
