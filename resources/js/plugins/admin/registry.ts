import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';

/**
 * What one Admin Panel section declares — plain data only. A section is
 * contributed by a Module through the duck-typed
 * {@link AdminSectionProvider.adminSections} method; the admin plugin pulls
 * the declarations from every registered Module once they all exist
 * (see {@link AdminRegistry.collect}) and registers their routes under
 * `/admin/<id>` itself. Nothing about the panel leaks into the kernel's
 * module or plugin contracts.
 */
export interface AdminSectionDefinition {
    /** Slug: the section's routes land under `/admin/<id>`, its group is named `admin.<id>`. */
    id: string;
    /** Translation key of the sidebar row's label (owned by the contributing plugin's namespace). */
    labelKey: string;
    /** Row icon in the admin sidebar. */
    icon?: IconComponent;
    /** Name of the section's index route — where the panel's entry navigates. */
    route: string;
    /**
     * Render-time visibility — the section's own gate. May read reactive
     * state (the assistants section reads the review-permission probe), so
     * consumers applying it inside a `$derived` re-evaluate when it flips.
     * Never a security boundary: the API stays authoritative.
     */
    visible: () => boolean;
    /** Routes registered inside the `/admin/<id>` group (runs at router-build time). */
    routes: (registrar: RouteRegistrar) => void;
}

/** A {@link AdminSectionDefinition} as stored, tagged with its contributing module. */
export interface RegisteredAdminSection extends AdminSectionDefinition {
    /** `${plugin}:${module}` of the contributing module, for error messages. */
    readonly module: string;
}

/** Handed to a module's `adminSections()` method; `section()` registers one section. */
export interface AdminSectionRegistrar {
    section(definition: AdminSectionDefinition): void;
}

/** A {@link HawkiModuleWithPlugin} that contributes admin sections. */
export interface AdminSectionProvider {
    adminSections(registrar: AdminSectionRegistrar): void;
}

/** Structural check for {@link AdminSectionProvider} — no kernel contract involved. */
export function providesAdminSections(value: unknown): value is AdminSectionProvider {
    return typeof value === 'object'
        && value !== null
        && typeof (value as AdminSectionProvider).adminSections === 'function';
}

/**
 * The Admin Panel's sections, collected once from every Module that
 * implements {@link AdminSectionProvider}. The admin plugin collects in its
 * `boot()` lifecycle (all modules exist by then) and registers the section
 * routes at router-build time, so contributors never depend on plugin
 * ordering. Kept free of Svelte imports so it can be exercised in Node tests.
 */
export class AdminRegistry {
    public readonly sections: RegisteredAdminSection[] = [];
    private readonly sectionsById = new Map<string, RegisteredAdminSection>();
    private collectionStarted = false;
    private _collected = false;

    /** Whether collection has run — admin routes must not register before it has. */
    public get collected(): boolean {
        return this._collected;
    }

    /**
     * Pulls the section declarations from every module: the admin plugin's
     * own modules first, then the others in module registration order.
     * One-shot — collecting twice means a second registry raced the first.
     */
    public collect(modules: HawkiModuleWithPlugin[]): void {
        if (this.collectionStarted) {
            throw new Error('Admin sections have already been collected.');
        }
        this.collectionStarted = true;

        const adminModules = modules.filter((module) => module.plugin.name === 'admin');
        const others = modules.filter((module) => module.plugin.name !== 'admin');

        try {
            for (const module of [...adminModules, ...others]) {
                if (providesAdminSections(module)) {
                    module.adminSections(this.registrarFor(module));
                }
            }
        } finally {
            this._collected = true;
        }
    }

    /** Sections whose own `visible()` gate currently passes — evaluated at render time. */
    public get visibleSections(): RegisteredAdminSection[] {
        return this.sections.filter((section) => section.visible());
    }

    private registrarFor(module: HawkiModuleWithPlugin): AdminSectionRegistrar {
        return {
            section: (definition) => this.registerSection(module, definition)
        };
    }

    private registerSection(module: HawkiModuleWithPlugin, definition: AdminSectionDefinition): void {
        const moduleName = `${module.plugin.name}:${module.name}`;
        if (!this.collectionStarted || this._collected) {
            throw new Error(`Module "${moduleName}" registered admin section "${definition.id}" outside collection.`);
        }
        if (this.sectionsById.has(definition.id)) {
            const existing = this.sectionsById.get(definition.id)!;
            throw new Error(
                `Module "${moduleName}" cannot register admin section "${definition.id}" `
                + `because module "${existing.module}" already registered it.`
            );
        }

        const registered: RegisteredAdminSection = {...definition, module: moduleName};
        this.sectionsById.set(definition.id, registered);
        this.sections.push(registered);
    }
}

/** The panel's single registry instance (the admin plugin and its sidebar share it). */
export const adminRegistry = new AdminRegistry();
