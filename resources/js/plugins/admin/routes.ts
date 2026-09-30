import type { RouteRegistrar } from '$lib/components/ui/routing/index.js';
import { authMetaGuards } from '$lib/kernel/routing/middlewares/AuthMiddleware.js';
import type { AdminRegistry } from './registry.js';

export function registerAdminRoutes(registrar: RouteRegistrar, registry: AdminRegistry) {
    registrar.lazyRoute('/', () => import('./pages/AdminHome.svelte'), {
        name: 'admin.index',
        meta: { title: 'admin.title', access: 'server-session', permission: 'admin.access' }
    });
    if (!registry.collected) {
        throw new Error('Admin routes cannot be registered before the Admin Registry has collected Module Workspaces.');
    }
    for (const workspace of registry.workspaces) {
        registrar.lazyRoute(workspace.path, workspace.page, {
            name: workspace.routeName,
            middlewares: [authMetaGuards({ access: 'server-session', permission: 'admin.access' })],
            meta: {
                title: workspace.title,
                access: 'server-session',
                permission: workspace.permission
            }
        });
    }
    // The Publishing Center's per-assistant detail page: a sub-route of the
    // `assistants` Workspace, which a Workspace definition cannot express on
    // its own (one `page` only), so it is registered directly here.
    registrar.lazyRoute('/assistants/:id', () => import('$plugins/assistants/admin/pages/AssistantDetail.svelte'), {
        name: 'admin.assistants.detail',
        middlewares: [authMetaGuards({ access: 'server-session', permission: 'admin.access' })],
        meta: {
            title: 'admin.sections.assistants',
            access: 'server-session',
            permission: 'assistants.manage'
        }
    });
}
