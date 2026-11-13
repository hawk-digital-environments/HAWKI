import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {AdminSectionProvider, AdminSectionRegistrar} from '$plugins/admin/registry.js';
import BadgeCheckIcon from '$lib/components/ui/icons/iconset/BadgeCheckIcon.svelte';
import {mayReviewAssistants} from '$plugins/assistants/admin/reviewPermission.svelte.js';

/**
 * The assistants plugin's contribution to the Admin Panel: the assistant
 * review section (see `plugins/admin/registry.ts`). Its gate is the
 * review-permission probe — org admins only; the API stays the boundary.
 */
export class AssistantAdminModule implements HawkiModule, AdminSectionProvider {
    readonly name = 'assistant-admin';

    public adminSections({section}: AdminSectionRegistrar): void {
        section({
            id: 'assistants',
            labelKey: 'assistants.admin.section',
            icon: BadgeCheckIcon,
            route: 'admin.assistants.index',
            visible: () => mayReviewAssistants() === true,
            routes: (registrar) => {
                registrar.lazyRoute(
                    '/',
                    () => import('$plugins/assistants/admin/pages/ReviewQueue.svelte'),
                    {name: 'admin.assistants.index'}
                );

                // The two-step detail flow: Changes, then Review. The group
                // layout owns the shared review fetch; the root redirects to
                // the first step (see ReviewDetailIndex).
                registrar.group('/:assistantId', (detail) => {
                    detail.lazyRoute(
                        '/',
                        () => import('$plugins/assistants/admin/pages/ReviewDetailIndex.svelte'),
                        {name: 'admin.assistants.detail'}
                    );
                    detail.lazyRoute(
                        '/changes',
                        () => import('$plugins/assistants/admin/pages/ReviewChanges.svelte'),
                        {name: 'admin.assistants.changes'}
                    );
                    detail.lazyRoute(
                        '/review',
                        () => import('$plugins/assistants/admin/pages/ReviewDecide.svelte'),
                        {name: 'admin.assistants.review'}
                    );
                }, {
                    lazyLayout: async () => (await import('$plugins/assistants/admin/pages/ReviewDetailLayout.svelte')).default
                });
            }
        });
    }
}
