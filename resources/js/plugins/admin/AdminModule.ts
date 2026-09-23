import type { HawkiModule } from '$lib/kernel/modules/types.js';
import type { HawkiApp } from '$lib/kernel/HawkiApp.js';
import type { Translator } from '$lib/kernel/localization/translator.js';
import type { RouteRegistrar } from '$lib/components/ui/routing/index.js';
import type { RouteComponentLoader } from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import AiBrainIcon from '$lib/components/ui/icons/iconset/AiBrainIcon.svelte';
import UserGroupIcon from '$lib/components/ui/icons/iconset/UserGroupIcon.svelte';
import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
import AdminSidebar from './components/AdminSidebar.svelte';
import type { AdminWorkspaceProvider, AdminWorkspaceRegistrar } from './api.js';
import type { AdminRegistry } from './registry.js';
import { registerAdminRoutes } from './routes.js';
import { builtInWorkspaces, type WorkspaceId } from './workspaces.js';

const pages = {
    'providers': () => import('./pages/AdminProviders.svelte'),
    'models': () => import('./pages/AdminModels.svelte'),
    'system-models': () => import('./pages/AdminSystemModels.svelte'),
    'mcp': () => import('./pages/AdminMcp.svelte'),
    'users': () => import('./pages/AdminUsers.svelte'),
    'announcements': () => import('./pages/AdminAnnouncements.svelte'),
    'usage': () => import('./pages/AdminUsage.svelte'),
    'health': () => import('./pages/AdminHealth.svelte'),
    'settings': () => import('./pages/AdminSettings.svelte'),
    'environment': () => import('./pages/AdminEnvironment.svelte')
} satisfies Record<WorkspaceId, RouteComponentLoader>;

export class AdminModule implements HawkiModule, AdminWorkspaceProvider {
    readonly name = 'admin';
    public constructor(private readonly registry: AdminRegistry) {}

    title(translate: Translator['translate']) {
        return translate('admin.title');
    }
    icon() {
        return Settings01Icon;
    }
    sidebar() {
        return AdminSidebar;
    }
    visible(app: HawkiApp) {
        return app.isAdmin;
    }

    adminWorkspaces({ section, workspace }: AdminWorkspaceRegistrar) {
        section({ id: 'ai', icon: AiBrainIcon, title: 'admin.groups.ai' });
        section({ id: 'people', icon: UserGroupIcon, title: 'admin.groups.people' });
        section({ id: 'system', icon: Settings01Icon, title: 'admin.groups.system' });
        for (const entry of builtInWorkspaces) {
            workspace({
                ...entry,
                title: `admin.sections.${entry.id}`,
                description: `admin.descriptions.${entry.id}`,
                page: pages[entry.id]
            });
        }
    }

    routes(registrar: RouteRegistrar) {
        registerAdminRoutes(registrar, this.registry);
    }
}
