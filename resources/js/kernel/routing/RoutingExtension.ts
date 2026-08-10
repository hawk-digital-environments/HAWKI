import type {HawkiAppExtension, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import UniversalRouter from 'universal-router';
import {RouteRegistrar, type RouteRenderer} from '$lib/kernel/routing/RouteRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface AppExtensions {
        router: UniversalRouter;
    }
}

export class RoutingExtension implements HawkiAppExtension {
    public readonly registrar = new RouteRegistrar();
    private _router: UniversalRouter | null = null;

    constructor(
        private readonly routeRenderer: RouteRenderer
    ) {
    }

    public get router(): UniversalRouter {
        if (!this._router) {
            throw new Error('Router is not initialized yet. Call init() first.');
        }
        return this._router;
    }

    public async init(app: UnfinishedHawkiApp) {
        await app.getOrFail('plugins').bootstrapper.runRoutes(this.registrar);

        for (const module of app.modules!.all) {
            if (typeof module.routes === 'function') {
                await module.routes(this.registrar);
            }
        }

        const routes = await this.registrar.build(this.routeRenderer);
        this._router = new UniversalRouter(routes);
    }

    public provideProperties(): Record<string, any> {
        return {
            router: this.router
        };
    }
}
