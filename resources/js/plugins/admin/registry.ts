import type { HawkiModuleWithPlugin } from '$lib/kernel/modules/types.js';
import { getPluginRoutePrefix } from '$lib/kernel/routing/routeInflection.js';
import { valueToSlug } from '$lib/utils/strings.js';
import type {
    AdminSectionDefinition,
    AdminWorkspaceDefinition,
    AdminWorkspaceProvider,
    AdminWorkspaceRegistrar,
    RegisteredAdminSection,
    RegisteredAdminWorkspace
} from './api.js';

export function providesAdminWorkspaces(value: unknown): value is AdminWorkspaceProvider {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as AdminWorkspaceProvider).adminWorkspaces === 'function'
    );
}

/**
 * The Admin Panel's Sections and Workspaces, collected once from every Module
 * that implements {@link AdminWorkspaceProvider}. The admin Module's own
 * declarations come first, then the others in Module registration order.
 * Kept free of Svelte imports so it can be exercised in Node tests.
 */
export class AdminRegistry {
    public readonly sections: RegisteredAdminSection[] = [];
    public readonly workspaces: RegisteredAdminWorkspace[] = [];
    private readonly sectionsByName = new Map<string, RegisteredAdminSection>();
    private readonly workspacesByName = new Map<string, RegisteredAdminWorkspace>();
    private readonly coreWorkspacesById = new Map<string, RegisteredAdminWorkspace>();
    private readonly workspacesByPath = new Map<string, RegisteredAdminWorkspace>();
    private collectionStarted = false;
    private _collected = false;

    public get collected(): boolean {
        return this._collected;
    }

    public workspace(idOrName: string): RegisteredAdminWorkspace | undefined {
        return idOrName.includes(':') ? this.workspacesByName.get(idOrName) : this.coreWorkspacesById.get(idOrName);
    }

    public collect(modules: HawkiModuleWithPlugin[]): void {
        if (this.collectionStarted) throw new Error('Admin Workspaces have already been collected.');
        this.collectionStarted = true;

        const adminModule = modules.find((module) => module.plugin.name === 'admin' && module.name === 'admin');
        const ordered = adminModule ? [adminModule, ...modules.filter((module) => module !== adminModule)] : modules;

        try {
            for (const module of ordered) {
                if (providesAdminWorkspaces(module)) module.adminWorkspaces(this.registrarFor(module));
            }
        } finally {
            this._collected = true;
        }
    }

    private registrarFor(module: HawkiModuleWithPlugin): AdminWorkspaceRegistrar {
        return {
            section: (definition) => this.registerSection(module, definition),
            workspace: (definition) => this.registerWorkspace(module, definition)
        };
    }

    private registerSection(module: HawkiModuleWithPlugin, definition: AdminSectionDefinition): void {
        const moduleName = `${module.plugin.name}:${module.name}`;
        if (this._collected) {
            throw new Error(
                `Module "${moduleName}" declared admin section "${definition.id}" after collection finished.`
            );
        }
        const name = `${moduleName}:${definition.id}`;
        const existing = this.sections.find((section) => section.id === definition.id);
        if (existing) {
            throw new Error(
                `Module "${moduleName}" cannot register admin section "${name}" because module "${existing.module}" already registered it.`
            );
        }

        const section: RegisteredAdminSection = { ...definition, name, module: moduleName, workspaces: [] };
        this.sectionsByName.set(name, section);
        this.sections.push(section);
    }

    private registerWorkspace(module: HawkiModuleWithPlugin, definition: AdminWorkspaceDefinition): void {
        const moduleName = `${module.plugin.name}:${module.name}`;
        if (this._collected) {
            throw new Error(
                `Module "${moduleName}" declared admin workspace "${definition.id}" after collection finished.`
            );
        }
        const name = `${moduleName}:${definition.id}`;
        const existingName = this.workspacesByName.get(name);
        if (existingName) {
            throw new Error(
                `Module "${moduleName}" cannot register admin workspace "${name}" because module "${existingName.module}" already registered it.`
            );
        }

        const section =
            this.sectionsByName.get(`${moduleName}:${definition.section}`) ??
            this.sectionsByName.get(`admin:admin:${definition.section}`);
        if (!section) {
            throw new Error(
                `Module "${moduleName}" cannot register admin workspace "${definition.id}" in unknown section "${definition.section}".`
            );
        }

        const pluginSlug = valueToSlug(module.plugin.name);
        const workspaceSlug = valueToSlug(definition.id);
        const path = `${getPluginRoutePrefix(module.plugin.name, module.plugin.isCorePlugin)}/${workspaceSlug}`;
        const routeName =
            module.plugin.isCorePlugin ? `admin.${workspaceSlug}` : `admin.plugins.${pluginSlug}.${workspaceSlug}`;
        const existingPath = this.workspacesByPath.get(path);
        if (existingPath) {
            throw new Error(
                `Module "${moduleName}" cannot register admin workspace "${definition.id}" at "${path}" because module "${existingPath.module}" already registered workspace "${existingPath.name}" there.`
            );
        }

        const workspace: RegisteredAdminWorkspace = {
            ...definition,
            name,
            module: moduleName,
            section,
            path,
            routeName
        };
        this.workspacesByName.set(name, workspace);
        this.workspacesByPath.set(path, workspace);
        if (module.plugin.isCorePlugin) this.coreWorkspacesById.set(definition.id, workspace);
        this.workspaces.push(workspace);
        section.workspaces.push(workspace);
    }
}
