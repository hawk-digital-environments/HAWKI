/**
 * The declaration side of search: what modules registered, before anything
 * runs.
 *
 * A module declares its groups and providers synchronously from
 * `HawkiModule.search()` while modules are being registered — no stores, no
 * fetches, no workers at that point. The registrar collected here is
 * **transactional and module-scoped**: it builds into local arrays and only
 * hands them to {@link SearchRegistry} once every name, kind and id has
 * validated, so a module that throws half-way through its declarations leaves
 * nothing behind (and cannot leave the registry with a group that has no
 * providers, or a provider whose group was rejected).
 *
 * Ownership is never a parameter. `moduleId`/`pluginId` come from the
 * registration context, so a module physically cannot declare groups for
 * another plugin or module.
 */
import type {
    DynamicSource,
    ModuleSearchRegistrar,
    SearchGroupKind,
    SearchGroupOptions,
    SearchGroupRegistrar,
    StaticSource
} from '$lib/kernel/search/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';

/** A group as declared, with the kernel-assigned id and its registration order. */
export interface SearchGroupDefinition {
    /** `${moduleId}.${name}`, e.g. `core:chat.messages`. */
    readonly id: string;
    readonly name: string;
    readonly moduleId: string;
    readonly pluginId: string;
    readonly kind: SearchGroupKind;
    readonly label: (translate: Translator['translate']) => string;
    /** Global declaration order; the tiebreaker for equally relevant groups. */
    readonly order: number;
}

/** A provider as declared. Provider ids live in their own registry, so a group and a provider may share a name. */
export interface SearchProviderDefinition {
    /** `${moduleId}.${name}`, e.g. `core:chat.messages`. */
    readonly id: string;
    readonly name: string;
    readonly groupId: string;
    readonly moduleId: string;
    readonly pluginId: string;
    readonly kind: SearchGroupKind;
    /** Global declaration order; the tiebreaker for equally relevant rows. */
    readonly order: number;
    readonly source: StaticSource | DynamicSource;
    /** Always `immediate` for dynamic providers; static providers may opt into `worker`. */
    readonly matchIn: 'immediate' | 'worker';
}

/** One module's complete, validated declaration set. */
export interface ModuleSearchDefinitions {
    readonly moduleId: string;
    readonly groups: readonly SearchGroupDefinition[];
    readonly providers: readonly SearchProviderDefinition[];
}

/** A name is used verbatim as an id segment, so the `.`/`:` separators and whitespace are out. */
const VALID_NAME = /^[^\s.:]+$/u;

interface CollectOptions {
    moduleId: string;
    pluginId: string;
    declare: (registrar: ModuleSearchRegistrar) => void;
    /** Ids already claimed by *other* modules; a clash aborts the whole module. */
    isGroupIdTaken?: (id: string) => boolean;
    isProviderIdTaken?: (id: string) => boolean;
    /** Where this module's declarations start in the global order. */
    nextOrder?: () => number;
}

/**
 * Runs `declare` against a scoped registrar and returns the resulting
 * definitions, or throws without producing anything.
 *
 * Rejected outright: blank or separator-bearing names, a group name declared
 * twice by the same module, a provider name declared twice by the same
 * module, an id another module already owns, a source whose shape contradicts
 * its group's `kind`, and a group that ends up with no providers at all.
 */
export function collectModuleSearchDefinitions(options: CollectOptions): ModuleSearchDefinitions {
    const {moduleId, pluginId, declare} = options;
    const isGroupIdTaken = options.isGroupIdTaken ?? (() => false);
    const isProviderIdTaken = options.isProviderIdTaken ?? (() => false);
    let orderCounter = 0;
    const nextOrder = options.nextOrder ?? (() => orderCounter++);

    const groups: SearchGroupDefinition[] = [];
    const providers: SearchProviderDefinition[] = [];
    const groupNames = new Set<string>();
    const providerNames = new Set<string>();
    let collecting = true;

    function group(name: string, groupOptions: SearchGroupOptions & {kind: SearchGroupKind}): SearchGroupRegistrar<any> {
        if (!collecting) throw new Error('Search declarations must run synchronously during module registration.');
        assertName(moduleId, 'group', name);
        if (groupOptions?.kind !== 'static' && groupOptions?.kind !== 'dynamic') {
            throw new Error(`Search group "${moduleId}.${name}" must declare kind "static" or "dynamic".`);
        }
        if (typeof groupOptions.label !== 'function') {
            throw new Error(`Search group "${moduleId}.${name}" must declare a "label" callback.`);
        }
        if (groupNames.has(name)) {
            throw new Error(`Search group "${name}" is already declared by module "${moduleId}".`);
        }
        const id = `${moduleId}.${name}`;
        if (isGroupIdTaken(id)) {
            throw new Error(`Search group "${id}" is already registered.`);
        }
        groupNames.add(name);

        const definition: SearchGroupDefinition = {
            id,
            name,
            moduleId,
            pluginId,
            kind: groupOptions.kind,
            label: groupOptions.label,
            order: nextOrder()
        };
        groups.push(definition);

        const registrar: SearchGroupRegistrar<any> = {
            add(providerName: string, source: StaticSource | DynamicSource) {
                addProvider(definition, providerName, source);
                return registrar;
            }
        };
        return registrar;
    }

    function addProvider(groupDefinition: SearchGroupDefinition, name: string, source: StaticSource | DynamicSource): void {
        if (!collecting) throw new Error('Search declarations must run synchronously during module registration.');
        assertName(moduleId, 'provider', name);
        if (providerNames.has(name)) {
            throw new Error(`Search provider "${name}" is already declared by module "${moduleId}".`);
        }
        const id = `${moduleId}.${name}`;
        if (isProviderIdTaken(id)) {
            throw new Error(`Search provider "${id}" is already registered.`);
        }
        assertSourceKind(groupDefinition, id, source);
        providerNames.add(name);

        providers.push({
            id,
            name,
            groupId: groupDefinition.id,
            moduleId,
            pluginId,
            kind: groupDefinition.kind,
            order: nextOrder(),
            source,
            matchIn: groupDefinition.kind === 'static' && (source as StaticSource).matchIn === 'worker' ? 'worker' : 'immediate'
        });
    }

    try {
        const result: unknown = declare({group} as ModuleSearchRegistrar);
        if (result instanceof Promise) {
            void result.catch(() => undefined);
            throw new Error(`Search declarations for module "${moduleId}" must be synchronous.`);
        }
    } finally {
        collecting = false;
    }

    for (const definition of groups) {
        if (!providers.some(provider => provider.groupId === definition.id)) {
            throw new Error(`Search group "${definition.id}" was declared without any provider.`);
        }
    }

    return {moduleId, groups, providers};
}

/**
 * The kernel's live view of every module's declarations.
 *
 * Registration is all-or-nothing per module (see
 * {@link collectModuleSearchDefinitions}) and reversible per module, because
 * a module can be removed at runtime and its providers then have to release
 * their subscriptions and drop their entries.
 */
export class SearchRegistry {
    private readonly groups = new Map<string, SearchGroupDefinition>();
    private readonly providers = new Map<string, SearchProviderDefinition>();
    private readonly byModule = new Map<string, ModuleSearchDefinitions>();
    private order = 0;
    private readonly listeners = new Set<() => void>();

    public subscribe(listener: () => void): () => void {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    /** Every group, in declaration order. */
    public get allGroups(): readonly SearchGroupDefinition[] {
        return [...this.groups.values()].sort(byOrder);
    }

    /** Every provider, in declaration order. */
    public get allProviders(): readonly SearchProviderDefinition[] {
        return [...this.providers.values()].sort(byOrder);
    }

    public group(id: string): SearchGroupDefinition | null {
        return this.groups.get(id) ?? null;
    }

    public provider(id: string): SearchProviderDefinition | null {
        return this.providers.get(id) ?? null;
    }

    public hasModule(moduleId: string): boolean {
        return this.byModule.has(moduleId);
    }

    /**
     * Collects and commits one module's declarations. Returns `null` when the
     * module declares no `search()` at all; throws (having changed nothing)
     * when its declarations are invalid.
     */
    public registerModule(moduleId: string, module: HawkiModuleWithPlugin): ModuleSearchDefinitions | null {
        const declare = module.search?.bind(module);
        if (!declare) {
            return null;
        }
        if (this.byModule.has(moduleId)) {
            throw new Error(`Search definitions for module "${moduleId}" are already registered.`);
        }

        const definitions = collectModuleSearchDefinitions({
            moduleId,
            pluginId: module.plugin.name,
            declare,
            isGroupIdTaken: id => this.groups.has(id),
            isProviderIdTaken: id => this.providers.has(id),
            nextOrder: () => this.order++
        });

        for (const group of definitions.groups) {
            this.groups.set(group.id, group);
        }
        for (const provider of definitions.providers) {
            this.providers.set(provider.id, provider);
        }
        this.byModule.set(moduleId, definitions);
        for (const listener of this.listeners) listener();

        return definitions;
    }

    /** Drops a module's declarations. Returns what was removed, or `null` if it had none. */
    public unregisterModule(moduleId: string): ModuleSearchDefinitions | null {
        const definitions = this.byModule.get(moduleId);
        if (!definitions) {
            return null;
        }
        for (const group of definitions.groups) {
            this.groups.delete(group.id);
        }
        for (const provider of definitions.providers) {
            this.providers.delete(provider.id);
        }
        this.byModule.delete(moduleId);
        for (const listener of this.listeners) listener();
        return definitions;
    }
}

function byOrder(a: {order: number}, b: {order: number}): number {
    return a.order - b.order;
}

function assertName(moduleId: string, what: 'group' | 'provider', name: string): void {
    if (typeof name !== 'string' || name.trim() === '' || !VALID_NAME.test(name)) {
        throw new Error(`Module "${moduleId}" declared a search ${what} with an invalid name "${String(name)}"; names must be non-empty and free of whitespace, "." and ":".`);
    }
}

/**
 * Guards the one invariant TypeScript cannot catch across a plugin boundary:
 * a static group only accepts sources that expose `items`, a dynamic group
 * only sources that expose `search`.
 */
function assertSourceKind(group: SearchGroupDefinition, providerId: string, source: StaticSource | DynamicSource): void {
    if (!source || typeof source !== 'object') {
        throw new Error(`Search provider "${providerId}" must be an object.`);
    }
    const hasItems = typeof (source as StaticSource).items === 'function';
    const hasSearch = typeof (source as DynamicSource).search === 'function';

    if (group.kind === 'static' && !hasItems) {
        throw new Error(`Search provider "${providerId}" is registered in the static group "${group.id}" but does not implement "items".`);
    }
    if (group.kind === 'dynamic' && !hasSearch) {
        throw new Error(`Search provider "${providerId}" is registered in the dynamic group "${group.id}" but does not implement "search".`);
    }
    if (group.kind === 'static' && hasSearch) {
        throw new Error(`Search provider "${providerId}" implements "search" but the group "${group.id}" is static; declare a separate dynamic group instead of mixing kinds.`);
    }
    if (group.kind === 'dynamic' && hasItems) {
        throw new Error(`Search provider "${providerId}" implements "items" but the group "${group.id}" is dynamic; declare a separate static group instead of mixing kinds.`);
    }
}
