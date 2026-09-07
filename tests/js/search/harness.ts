/**
 * A minimal stand-in for everything the session manager's host provides: a
 * registry, a real shared index, an in-memory local storage and controllable
 * worker replies. Deliberately not a mock framework — the tests assert on
 * observable session output, and this only supplies the inputs.
 */
import {SearchRegistry, type SearchProviderDefinition} from '$lib/kernel/search/searchRegistry.js';
import {SharedSearchIndex} from '$lib/kernel/search/sharedIndex.js';
import type {SearchSessionHost} from '$lib/kernel/search/sessionHost.js';
import type {ModuleSearchRegistrar, SearchEntry, SearchProviderError, StaticSource} from '$lib/kernel/search/types.js';
import type {SearchScores} from '$lib/kernel/search/searchEngine.js';

export class MemoryStorage {
    public readonly items = new Map<string, string>();

    public getItem(key: string): string | null {
        return this.items.get(key) ?? null;
    }

    public setItem(key: string, value: string): void {
        this.items.set(key, value);
    }

    public removeItem(key: string): void {
        this.items.delete(key);
    }
}

export interface WorkerReply {
    revision: number;
    scores: SearchScores;
}

export class TestSearchHost implements SearchSessionHost {
    public readonly registry = new SearchRegistry();
    public readonly index = new SharedSearchIndex();
    public readonly storage = new MemoryStorage();
    public readonly app: any;
    public identity: string | null = 'user-1@connection-a';
    public errors: SearchProviderError[] = [];

    /** Answers `queryWorker`. Replace per test; the default never resolves on its own. */
    public worker: (query: string, signal: AbortSignal) => Promise<WorkerReply> =
        () => new Promise<WorkerReply>(() => undefined);

    public readonly retriedStatic: string[] = [];
    public retriedWorker = 0;

    private readonly active = new Map<string, {definition: SearchProviderDefinition; controller: AbortController}>();

    public constructor() {
        this.app = {localStorage: this.storage};

    }

    public get providers(): readonly {definition: SearchProviderDefinition; signal: AbortSignal}[] {
        return [...this.active.values()].map(({definition, controller}) => ({definition, signal: controller.signal}));
    }

    /** Registers one module's declarations and activates every provider it declared. */
    public register(moduleId: string, pluginId: string, declare: (registrar: ModuleSearchRegistrar) => void): void {
        const definitions = this.registry.registerModule(moduleId, {plugin: {name: pluginId}, search: declare} as any);
        for (const definition of definitions?.providers ?? []) {
            this.active.set(definition.id, {definition, controller: new AbortController()});
            this.reindex(definition.id);
        }
    }

    /** Re-reads a static provider's items, the way the extension's observation would. */
    public reindex(providerId: string): void {
        const entry = this.active.get(providerId);
        if (!entry || entry.definition.kind !== 'static') {
            return;
        }
        const items = (entry.definition.source as StaticSource).items({app: this.app, signal: entry.controller.signal});
        this.index.setProviderEntries(entry.definition, items as readonly SearchEntry[]);
    }

    /** Disables a provider: aborts its work and drops its entries, as deactivation does. */
    public deactivate(providerId: string): void {
        const entry = this.active.get(providerId);
        if (!entry) {
            return;
        }
        entry.controller.abort();
        this.active.delete(providerId);
        this.index.removeProvider(providerId);
    }

    public groupLabel(groupId: string): string {
        const group = this.registry.group(groupId);
        return group === null ? groupId : group.label(((label: string) => label) as any);
    }

    public retryStatic(providerId: string): void {
        this.retriedStatic.push(providerId);
    }

    public retryWorker(): void {
        this.retriedWorker++;
    }

    public queryWorker(query: string, signal: AbortSignal): Promise<{revision: number; scores: SearchScores}> {
        return this.worker(query, signal);
    }
}

/** A static source over a fixed list; `entries` may be mutated between reindexes. */
export function staticSourceOf(entries: SearchEntry[], matchIn: 'immediate' | 'worker' = 'immediate'): StaticSource {
    return {matchIn, items: () => entries};
}

export function entry(id: string, title: string, extra: Partial<SearchEntry> = {}): SearchEntry {
    return {id, entityKey: `entity/${id}`, title, onSelect: () => undefined, ...extra};
}

/** Lets pending promise callbacks and microtasks run. */
export function tick(ms = 0): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
}

export function rowTitles(groups: readonly {items: readonly {title: string}[]}[]): string[] {
    return groups.flatMap(group => group.items.map(item => item.title));
}
