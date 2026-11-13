import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {HookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';
import {HawkiPlugin} from '$lib/kernel/plugins/types';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
import AdminSidebar from '$plugins/admin/AdminSidebar.svelte';
import {adminRegistry} from '$plugins/admin/registry.js';

/**
 * The Admin Panel — a shell over the sections other modules contribute.
 *
 * Sections are declared by modules through the duck-typed `adminSections()`
 * method (see `registry.ts`); the panel pulls every declaration in `boot()`,
 * registers each section's routes under `/admin/<id>` and renders its
 * sidebar from them. Visibility is the sections' own call: the entry and
 * sidebar render only while at least one section's `visible()` gate passes.
 * Never a security boundary — the API stays authoritative.
 */
export default class AdminPlugin implements HawkiPlugin {
    readonly name = 'admin';

    /** Pull the section declarations once all modules exist, before the router applies them. */
    public boot(app: HawkiApp): void {
        adminRegistry.collect(app.modules.all);
    }

    /**
     * The `/admin` route group. The children callback runs at router-build
     * time — after `boot()` collected — so it sees the complete section list.
     */
    public routes(registrar: RouteRegistrar): void {
        registrar.group('/admin', (sections) => {
            if (!adminRegistry.collected) {
                throw new Error('Admin routes registered before the section registry was collected.');
            }

            for (const section of adminRegistry.sections) {
                sections.group(`/${section.id}`, section.routes, {name: `admin.${section.id}`});
            }
        }, {name: 'admin'});
    }

    public hooks(registrar: HookRegistrar): void | Promise<void> {
        registrar.add('moduleSelectorEntries', (entries, ctx) => {
            const visible = adminRegistry.visibleSections;
            if (visible.length === 0) {
                return entries;
            }

            return [
                ...entries,
                {
                    id: 'admin',
                    label: ctx.translate('admin.title'),
                    icon: Settings01Icon,
                    onSelect: (selectCtx) => {
                        void selectCtx.router.goToRoute(visible[0].route);
                    },
                    active: visible.some((section) => ctx.router.isRouteActive(`admin.${section.id}`))
                }
            ];
        });

        registrar.add('sidebarSlots', (slots, ctx) => [
            ...slots,
            {
                id: 'admin:sidebar',
                position: 'panel',
                component: AdminSidebar,
                active: ctx.router.isRouteActive('admin')
            }
        ]);
    }
}
