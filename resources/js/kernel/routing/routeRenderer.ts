import type {RouteRenderer} from '$lib/kernel/routing/RouteRegistrar.js';

export function createDefaultRouteRenderer(): RouteRenderer {
    return (componentOrLoader, context, params) => {
        console.log('Rendering route with component or loader:', componentOrLoader, 'context:', context, 'params:', params);
        return null; // @todo: Implement actual rendering logic here.
    };
}
