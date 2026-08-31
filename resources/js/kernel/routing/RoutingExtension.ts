import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import {createRouterFromRegistrar, RouteRegistrar, type Router, type RouterHandle} from '$lib/components/ui/routing/index.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {RestApi} from '$lib/kernel/api/RestApi.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        router: RouterHandle;
    }
}

// Extends the router context with the app and restApi so the router itself
// does not need to know about our app or restApi (which comes in handy,
// when we externalize the components into their own package).
declare module '$lib/components/ui/routing/extendableTypes.js' {
    interface RouteContextExtensions {
        app: HawkiApp;
        restApi: RestApi;
    }
}

/**
 * App extension that owns the frontend router: it collects every route
 * registration contributed by plugins and modules, compiles them into a
 * `universal-router` route tree, and exposes the resulting `UniversalRouter`
 * as `app.router`.
 *
 * Routes are not auto-discovered. Two sources feed the single shared
 * {@link RouteRegistrar} during {@link init}, in this order:
 *
 * 1. Every plugin's `routes(registrar, context)` hook, dispatched through
 *    `PluginBootstrapper.runRoutes()` (see `$lib/kernel/plugins/types.js`).
 *    `runRoutes` already wrapped each hook in a `registrar.group(...)` carrying
 *    the plugin's route prefix (see `getPluginRoutePrefix` in
 *    `routeInflection.js`) — empty for core plugins, `/plugins/<slug>`
 *    otherwise — so plugin-level routes are namespaced the same way module
 *    routes are.
 * 2. Every registered module's `routes(registrar)` hook (see
 *    `$lib/kernel/modules/types.js`). Modules are collected earlier by
 *    `ModuleExtension`, which is why `RoutingExtension` must be listed *after*
 *    it in `resources/js/app.ts`. `createModuleRegistrar` already wrapped each
 *    module hook in a `registrar.group(...)` carrying the module's route prefix
 *    (see `getModuleRoutePrefix` in `routeInflection.js`), so module routes are
 *    namespaced automatically.
 *
 * Turning a matched route into something renderable is deliberately *not* this
 * extension's concern: every compiled route's `action` (built by
 * `RouteRegistrar`'s `buildRouteFromOptions()`/`buildRouteGroupFromOptions()`)
 * just returns a `RouteResultBody` — `{component, context, params}` — and
 * `createRouterFromRegistrar()` (`router.ts`) is what actually resolves
 * and renders it.
 */
export class RoutingExtension implements HawkiAppExtension {
    /**
     * The registrar that is handed to plugins and modules during {@link init}.
     * All registration sources share this one instance.
     *
     * Note that the router is a one-time snapshot: {@link init} calls
     * `registrar.build()` once at the end and never re-reads the registrar
     * afterwards, so registering routes after boot has no effect on
     * {@link router}.
     */
    public readonly registrar = new RouteRegistrar();
    private _router: Router | null = null;

    /**
     * The compiled router's `RouterHandle` (`goTo`, `getPath`, `isActive`, …).
     * Throws if accessed before the `late` boot stage has built it (see
     * {@link ready}).
     */
    public get router(): RouterHandle {
        if (!this._router) {
            throw new Error('Router is not initialized yet. It is built on the "late" boot stage.');
        }
        return this._router.handle;
    }

    /**
     * Collects all route registrations (plugins first, then modules — the
     * registration order also defines the order in which `universal-router`
     * tries to match them), compiles them via {@link RouteRegistrar.build} and
     * creates the {@link UniversalRouter} from the resulting route tree.
     *
     * Requires `plugins` and `modules` to already be registered on the app, so
     * `RoutingExtension` must come after `PluginExtension` and `ModuleExtension`
     * in the extension list of `resources/js/app.ts`.
     */
    public async init(app: UnfinishedHawkiApp) {
        await app.getOrFail('plugins').bootstrapper.runRoutes(this.registrar);

        for (const module of app.modules!.all) {
            if (typeof module.routes === 'function') {
                await module.routes(this.registrar);
            }
        }
    }

    public ready(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void> {
        bootstrapper.onLateStage(() => {
            // @todo we could read the base path from the config here
            this._router = createRouterFromRegistrar('app', this.registrar, {
                // @todo this is a temporary construct, we should read the base path from the config instead of hardcoding it here
                basePath: '/new',
                strategy: 'path',
                // Supplies the `app`/`restApi` properties the `declare module`
                // block above adds to `RouteContextExtensions`, so every
                // middleware, route action and `loadData` resolved by this
                // router sees them on its context.
                context: {app, restApi: app.restApi}
            });
        });
    }

    /**
     * Exposes the compiled router as `app.router`. Unlike most extensions this
     * publishes the `UniversalRouter` itself (not the extension), and it reads
     * {@link router} eagerly — which is safe because `createApp()` always calls
     * `provideProperties()` right after `init()` has completed.
     */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get router() {
                return extension.router;
            },
            /**
             * @internal Exposes the compiled router as `app.__router`. This is not a public API! Do not use it in your code, it may change or be removed at any time.
             */
            get __router() {
                return extension._router;
            }
        };
    }
}
