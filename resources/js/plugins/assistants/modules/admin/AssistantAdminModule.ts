import type { HawkiModule } from '$lib/kernel/modules/types.js';
import type { AdminWorkspaceProvider, AdminWorkspaceRegistrar } from '$plugins/admin/api.js';
import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';

/**
 * Contributes the Publishing Center to the Admin Panel: its own Section (a
 * peer of the built-in `ai`/`people`/`system` ones) holding a single
 * Workspace, the assistants list page. The assistant detail route
 * (`admin.assistants.detail`) is registered directly by
 * `plugins/admin/routes.ts` alongside this Workspace's own route, since a
 * Workspace has no concept of sub-routes.
 */
export class AssistantAdminModule implements HawkiModule, AdminWorkspaceProvider {
    public readonly name = 'publishing';

    adminWorkspaces({ section, workspace }: AdminWorkspaceRegistrar): void {
        section({ id: 'assistants', icon: BotIcon, title: 'admin.groups.assistants' });
        workspace({
            id: 'assistants',
            section: 'assistants',
            permission: 'assistants.manage',
            title: 'admin.sections.assistants',
            description: 'admin.descriptions.assistants',
            page: () => import('$plugins/assistants/admin/pages/PublishingCenter.svelte')
        });
    }
}
