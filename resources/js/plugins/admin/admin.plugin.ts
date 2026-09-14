import type {HawkiPlugin} from '$lib/kernel/plugins/types.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import type {HookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';
import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
import AdminSidebar from './components/AdminSidebar.svelte';
import {AdminModule} from './AdminModule.js';

export default class AdminPlugin implements HawkiPlugin {
    readonly name = 'admin';

    modules({add}: ModuleRegistrar) { add(new AdminModule()); }

    resourceSchemas(registrar: ResourceSchemaRegistrar) {
        registrar.addFromModules(import.meta.glob('./schemas/*.schema.ts', {eager: true}));
    }

    /**
     * Contributes the admin feature's sidebar UI via the sidebar hooks: one
     * module selector entry and one sidebar panel, both active while an admin
     * route is shown, and both gated on `ctx.can('admin.access')` — they
     * disappear entirely for a user without the permission rather than
     * showing and failing on click.
     */
    public hooks(registrar: HookRegistrar): void {
        const adminGroup = getModuleRouteGroupName('admin', 'admin');

        registrar.add('moduleSelectorEntries', (entries, ctx) => {
            if (!ctx.can('admin.access')) {
                return entries;
            }
            return [...entries, {
                id: 'admin:admin',
                label: ctx.translate('admin.title'),
                icon: Settings01Icon,
                onSelect: (selectCtx) => {
                    void selectCtx.router.goToRoute('admin.index');
                },
                active: ctx.router.isRouteActive(adminGroup)
            }];
        });

        registrar.add('sidebarSlots', (slots, ctx) => {
            if (!ctx.can('admin.access')) {
                return slots;
            }
            return [...slots, {
                id: 'admin:sidebar',
                position: 'panel',
                component: AdminSidebar,
                active: ctx.router.isRouteActive(adminGroup)
            }];
        });
    }
}
