/**
 * Declaration-merging surface of the routing system, modeled on the kernel's
 * `$lib/kernel/extendableTypes.js`. It exports empty interfaces on purpose —
 * whoever wires the router into an application augments them via
 * `declare module './extendableTypes.js' { ... }`
 * instead of this package having to know about that application. Always import
 * the *type* (`import type {...}`) here; the module has no runtime exports.
 *
 * This file, not `index.ts`, is the augmentation target: declaration merging
 * has to name the module an interface is *declared* in, and a barrel re-export
 * is not a declaration site. Keeping the declarations here rather than in the
 * modules that consume them means the path an augmenter names is a public,
 * purpose-built one — `logistics/dataLoader.js` would be internal plumbing,
 * and would become published API the moment `components/ui` is externalized
 * into its own package.
 */

/**
 * Extra properties every `loadData` finds on its context, on top of the
 * routing-owned ones {@link import('./logistics/dataLoader.js').RouteDataLoaderContext}
 * declares itself.
 *
 * This exists so the routing package can hand loaders application-level
 * services without importing the application — the `components/ui` → `kernel`
 * direction is deliberately not a dependency. HAWKI's kernel augments it in
 * `$lib/kernel/routing/RoutingExtension.js`:
 * ```ts
 * declare module './extendableTypes.js' {
 *     interface RouteDataLoaderContextExtensions {
 *         app: HawkiApp;
 *         restApi: RestApi;
 *     }
 * }
 * ```
 *
 * Augmenting only makes the properties *visible*; the concrete values are
 * supplied per-router through
 * {@link import('./logistics/router.js').CreateRouterOptions.loaderContext},
 * so a router created without them would type-check but hand loaders a context
 * missing what they were promised. The two belong together.
 *
 * Note that `RouteCacheKeyContext` deliberately does *not* extend this — see
 * its doc comment for why a cache key must be computable without app services.
 */
export interface RouteDataLoaderContextExtensions {
    // Populated by the consuming application via declaration merging (see above).
}
