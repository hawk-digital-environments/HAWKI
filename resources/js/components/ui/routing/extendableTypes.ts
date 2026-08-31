/**
 * Declaration-merging surface of the routing system, modeled on the kernel's
 * `$lib/kernel/extendableTypes.js`. It exports empty interfaces on purpose —
 * whoever wires the router into an application augments them via
 * `declare module '$lib/components/ui/routing/extendableTypes.js' { ... }`
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
 * Extra properties merged into *every* route context this router produces — a
 * middleware's {@link import('./logistics/RouteRegistrar.js').HawkiRouteContext},
 * a route action's, and a
 * {@link import('./logistics/dataLoader.js').RouteDataLoaderContext} — on top
 * of the routing-owned properties each declares itself.
 *
 * This exists so the routing package can hand application-level services to
 * route code without importing the application — the `components/ui` →
 * `kernel` direction is deliberately not a dependency. HAWKI's kernel augments
 * it in `$lib/kernel/routing/RoutingExtension.js`:
 * ```ts
 * declare module '$lib/components/ui/routing/extendableTypes.js' {
 *     interface RouteContextExtensions {
 *         app: HawkiApp;
 *         restApi: RestApi;
 *     }
 * }
 * ```
 *
 * Augmenting only makes the properties *visible*; the concrete values are
 * supplied per-router through
 * {@link import('./logistics/router.js').CreateRouterOptions.context}, so a
 * router created without them would type-check but hand route code a context
 * missing what it was promised. The two belong together.
 *
 * Do not add a property named `router`, `route`, `params`, `path`, `baseUrl`,
 * `pathname` or `next`: those are `universal-router`'s own context fields, and
 * it spreads this object *over* them (`universal-router.js`'s `resolve()`), so
 * one of the two would silently disappear.
 *
 * Note that `RouteCacheKeyContext` deliberately does *not* extend this — see
 * its doc comment for why a cache key should be computable without app
 * services.
 */
export interface RouteContextExtensions {
    // Populated by the consuming application via declaration merging (see above).
}

export interface GlobalMiddlewares {

}
