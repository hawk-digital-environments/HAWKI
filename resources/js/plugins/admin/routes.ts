import type { RouteRegistrar } from '$lib/components/ui/routing/index.js';
import type { AdminRegistry } from './registry.js';

export function registerAdminRoutes(registrar: RouteRegistrar, registry: AdminRegistry) {
    registrar.lazyRoute('/', () => import('./pages/AdminHome.svelte'), {
        name: 'admin.index',
        meta: { title: 'admin.title', access: 'server-session', admin: true }
    });
    if (!registry.collected) {
        throw new Error('Admin routes cannot be registered before the Admin Registry has collected Module Workspaces.');
    }
    for (const workspace of registry.workspaces) {
        registrar.lazyRoute(workspace.path, workspace.page, {
            name: workspace.routeName,
            meta: {
                title: workspace.title,
                access: 'server-session',
                admin: true
            }
        });
    }
}
