/**
 * Public surface of the admin plugin for other Modules.
 *
 * Vocabulary (see `CONTEXT.md`, "Admin Panel"): a **Section** is a titled,
 * icon-bearing cluster in the panel's sidebar and overview (*AI services*,
 * *People and access*, *System*); a **Workspace** is one page inside a
 * Section, restricted to administrators; a **Record Set**
 * is one set of records a Workspace page works with (rows, writes, actions,
 * dialogs), and a page may hold several.
 *
 * A Module adds Sections and Workspaces by implementing
 * {@link AdminWorkspaceProvider} next to `HawkiModule`. Declarations are
 * static: no app state is available, visibility is evaluated by the panel at
 * render time. The admin plugin collects the declarations of every registered
 * Module once all Modules exist and before the router is compiled.
 *
 *     export class ChatModule implements HawkiModule, AdminWorkspaceProvider {
 *         readonly name = 'chat';
 *         adminWorkspaces({ workspace }: AdminWorkspaceRegistrar) {
 *             workspace({
 *                 id: 'prompts',
 *                 section: 'ai',
 *                 title: 'chat.admin.prompts',
 *                 description: 'chat.admin.prompts_description',
 *                 page: () => import('./admin/ChatPrompts.svelte')
 *             });
 *         }
 *     }
 */
import type { IconComponent } from '$lib/components/ui/icons/index.js';
import type { RouteComponentLoader } from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

export interface AdminSectionDefinition {
    /** Module-local id. Two Modules cannot declare the same Section. */
    id: string;
    icon: IconComponent;
    /** Translation key of the Section title. */
    title: string;
}

export interface AdminWorkspaceDefinition {
    /**
     * Module-local id, used as the URL segment. Workspaces of Modules from core
     * plugins share `/admin/<id>`, so their ids must be unique across those
     * Modules; third-party Workspaces live under `/admin/plugins/<plugin>/<id>`.
     */
    id: string;

    /**
     * Section the Workspace belongs to: the id of a Section this Module
     * declared, or of a built-in one (`'ai' | 'people' | 'system'`).
     */
    section: string;
    /** Translation key of the Workspace title (sidebar, overview, page heading, document title). */
    title: string;
    /** Translation key of the one-line description shown on the overview. */
    description: string;
    page: RouteComponentLoader;
}

/** Bound to one Module; ids are namespaced with the Module's full name. */
export interface AdminWorkspaceRegistrar {
    section(definition: AdminSectionDefinition): void;
    workspace(definition: AdminWorkspaceDefinition): void;
}

/** Implemented by a Module alongside `HawkiModule` to add Sections and Workspaces to the Admin Panel. */
export interface AdminWorkspaceProvider {
    adminWorkspaces(registrar: AdminWorkspaceRegistrar): void;
}

/** A Section as registered: the declaration plus its owner and the Workspaces placed in it, in registration order. */
export interface RegisteredAdminSection extends AdminSectionDefinition {
    /** `<plugin>:<module>:<id>` */
    name: string;
    /** Full name of the owning Module (`<plugin>:<module>`). */
    module: string;
    workspaces: RegisteredAdminWorkspace[];
}

/** A Workspace as registered: the declaration plus its owner, its Section and its route. */
export interface RegisteredAdminWorkspace extends Omit<AdminWorkspaceDefinition, 'section'> {
    /** `<plugin>:<module>:<id>` */
    name: string;
    /** Full name of the owning Module (`<plugin>:<module>`). */
    module: string;
    section: RegisteredAdminSection;
    /** Path relative to the admin module: `/<id>` for core plugins, `/plugins/<plugin-slug>/<id>` otherwise. */
    path: string;
    /** `admin.<id>` for core plugins, `admin.plugins.<plugin-slug>.<id>` otherwise. */
    routeName: string;
}

export { providesAdminWorkspaces } from './registry.js';

// Building blocks for Workspace pages.
export { useAdminRecordSet, AdminRecordSet } from './recordSet.svelte.js';
export type { AdminColumn, AdminAction, AdminReader, AdminRecordSetOptions } from './recordSet.svelte.js';
export type { AdminRow, AdminField } from './schemas/admin-content.js';
export { default as AdminPage } from './components/AdminPage.svelte';
export { default as AdminSearch } from './components/AdminSearch.svelte';
export { default as AdminTable } from './components/AdminTable.svelte';
export { default as AdminResultDialog } from './components/AdminResultDialog.svelte';
