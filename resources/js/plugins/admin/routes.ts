import type { RouteRegistrar } from '$lib/components/ui/routing/index.js';
import type { RouteComponentLoader } from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import { authMetaGuards } from '$lib/kernel/routing/middlewares/AuthMiddleware.js';
import { sections, type SectionId } from './sections.js';

const pages = {
    'providers': () => import('./pages/AdminProviders.svelte'),
    'models': () => import('./pages/AdminModels.svelte'),
    'system-models': () => import('./pages/AdminSystemModels.svelte'),
    'mcp': () => import('./pages/AdminMcp.svelte'),
    'users': () => import('./pages/AdminUsers.svelte'),
    'roles': () => import('./pages/AdminRoles.svelte'),
    'mappings': () => import('./pages/AdminMappings.svelte'),
    'announcements': () => import('./pages/AdminAnnouncements.svelte'),
    'usage': () => import('./pages/AdminUsage.svelte'),
    'health': () => import('./pages/AdminHealth.svelte'),
    'settings': () => import('./pages/AdminSettings.svelte'),
    'environment': () => import('./pages/AdminEnvironment.svelte')
} satisfies Record<SectionId, RouteComponentLoader>;

export function registerAdminRoutes(registrar: RouteRegistrar) {
    registrar.lazyRoute('/', () => import('./pages/AdminHome.svelte'), {
        name: 'admin.index',
        meta: { title: 'admin.title', access: 'server-session', permission: 'admin.access' }
    });
    for (const section of sections) {
        registrar.lazyRoute(`/${section.id}`, pages[section.id], {
            name: `admin.${section.id}`,
            middlewares: [authMetaGuards({ access: 'server-session', permission: 'admin.access' })],
            meta: {
                title: `admin.sections.${section.id}`,
                access: 'server-session',
                permission: section.permission
            }
        });
    }
}
