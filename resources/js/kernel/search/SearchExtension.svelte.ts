import {flushSync, untrack} from 'svelte';
import type {Readable} from 'svelte/store';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiApp, HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {SearchProviderDefinition, SearchRegistry} from './searchRegistry.js';
import type {SearchEntry, SearchProviderError, SearchScopeOptions, SearchSession, SearchSessionOptions, StaticSource} from './types.js';
import {SharedSearchIndex} from './sharedIndex.js';
import {SearchWorkerClient} from './SearchWorkerClient.js';
import {SearchSessionManager} from './SearchSessionManager.svelte.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        search: WithoutAppExtensionInternals<SearchExtension>;
    }
}

interface Activation {
    definition: SearchProviderDefinition;
    controller: AbortController;
    stopItems?: () => void;
}

/** Shared source observation and indexes. Each consumer owns a separate session. */
export class SearchExtension implements HawkiAppExtension {
    private app: HawkiApp | null = null;
    private registry: SearchRegistry | null = null;
    private readonly index = new SharedSearchIndex();
    private worker = new SearchWorkerClient(this.index);
    private manager: SearchSessionManager | null = null;
    private readonly monitors = new Map<string, () => void>();
    private readonly active = new Map<string, Activation>();
    private readonly errors = new Map<string, SearchProviderError>();
    private readonly labels = new Map<string, string>();
    private identity: string | null = null;
    private stopped = false;
    private initialized = false;
    private revision = $state(0);
    private _scopeOptions = $state.raw<SearchScopeOptions>({plugins: [], modules: []});
    private cleanup: (() => void)[] = [];

    public get scopeOptions(): SearchScopeOptions {
        return this._scopeOptions;
    }

    public ready(app: HawkiApp, bootstrapper: Bootstrapper): void {
        bootstrapper.onStagePassed('main', () => this.activate(app));
    }

    public createSession(options?: SearchSessionOptions): SearchSession {
        if (!this.manager) throw new Error('Search providers have not been activated yet.');
        return this.manager.createSession(options);
    }

    public dispose(): void {
        this.stopped = true;
        for (const stop of this.cleanup.splice(0)) stop();
        this.stopProviders();
        this.manager?.dispose();
        this.worker.dispose();
        this._scopeOptions = {plugins: [], modules: []};
    }

    public provideProperties(): Record<string, unknown> {
        return {search: this};
    }

    private activate(app: HawkiApp): void {
        if (this.initialized) return;
        this.initialized = true;
        this.app = app;
        this.registry = app.modules.searchRegistry;
        const extension = this;
        this.manager = new SearchSessionManager({
            app,
            registry: this.registry,
            index: this.index,
            get identity() { return extension.identity; },
            get providers() {
                return [...extension.active.values()].map(({definition, controller}) => ({definition, signal: controller.signal}));
            },
            get errors() {
                extension.revision;
                return [...extension.errors.values()];
            },
            groupLabel: id => this.labels.get(id) ?? id,
            retryStatic: id => this.retryStatic(id),
            queryWorker: (query, signal) => this.worker.search(query, signal),
            retryWorker: () => this.worker.retry()
        });
        this.cleanup.push(this.index.subscribe(() => this.manager?.invalidate()));
        this.cleanup.push(this.registry.subscribe(() => this.syncProviders()));
        this.cleanup.push(app.events.async.on('logout', () => this.dispose()));

        flushSync(() => {
            this.cleanup.push($effect.root(() => {
                $effect(() => {
                    const connection = app.connectionOrNull;
                    const identity = connection?.isAuthenticated
                        ? JSON.stringify([connection.id, connection.userinfo.id, connection.userinfo.hash])
                        : null;
                    untrack(() => {
                        if (this.stopped) return;
                        if (identity !== this.identity) {
                            this.stopProviders();
                            this.identity = identity;
                            this.manager?.reset();
                        }
                        this.syncProviders();
                    });
                });
                $effect(() => {
                    this.revision;
                    const groups = this.registry!.allGroups.map(group => [group.id, group.label(app.translator.translate)] as const);
                    const options = this.buildScopeOptions(app);
                    untrack(() => {
                        this.labels.clear();
                        for (const [id, label] of groups) this.labels.set(id, label);
                        this._scopeOptions = options;
                        this.manager?.invalidate();
                    });
                });
            }));
        });
    }

    private syncProviders(): void {
        if (this.stopped || !this.registry || !this.app) return;
        const providers = this.registry.allProviders;
        const ids = new Set(providers.map(provider => provider.id));
        for (const [id, stop] of this.monitors) {
            if (ids.has(id)) continue;
            stop();
            this.monitors.delete(id);
            this.deactivateProvider(id);
        }
        // Authentication loss must not expose private snapshots left in module stores.
        if (this.identity === null) {
            this.changed();
            return;
        }
        for (const provider of providers) {
            if (this.monitors.has(provider.id)) continue;
            const monitorController = new AbortController();
            const stop = $effect.root(() => {
                $effect(() => {
                    let enabled = false;
                    try {
                        enabled = provider.source.enabled?.({app: this.app!, signal: monitorController.signal}) ?? true;
                    } catch (error) {
                        untrack(() => this.fail(provider, error));
                    }
                    untrack(() => {
                        if (enabled && !this.active.has(provider.id)) this.activateProvider(provider);
                        else if (!enabled) this.deactivateProvider(provider.id);
                    });
                });
                return () => monitorController.abort();
            });
            this.monitors.set(provider.id, stop);
        }
        this.changed();
    }

    private activateProvider(definition: SearchProviderDefinition): void {
        if (this.stopped) return;
        const activation: Activation = {definition, controller: new AbortController()};
        this.active.set(definition.id, activation);
        this.errors.delete(definition.id);
        if (definition.kind === 'static') {
            const source = definition.source as StaticSource;
            const runtime = {app: this.app!, signal: activation.controller.signal};
            let unsubscribe: (() => void) | undefined;
            let currentStore: Readable<readonly SearchEntry[]> | undefined;
            let loadStarted = false;
            const accept = (items: readonly SearchEntry[]) => {
                if (runtime.signal.aborted) return;
                try {
                    // Snapshot fields while tracked, including nested edits and keyword arrays.
                    const entries = snapshotEntries(items);
                    untrack(() => this.index.setProviderEntries(definition, entries));
                } catch (error) {
                    untrack(() => {
                        this.index.removeProvider(definition.id);
                        this.fail(definition, error);
                    });
                }
            };
            const stop = $effect.root(() => {
                $effect(() => {
                    try {
                        const items = source.items(runtime);
                        if (isReadable(items)) {
                            if (currentStore !== items) {
                                unsubscribe?.();
                                currentStore = items;
                                unsubscribe = items.subscribe(accept);
                            }
                        } else {
                            unsubscribe?.();
                            unsubscribe = undefined;
                            currentStore = undefined;
                            accept(items);
                        }
                        if (!loadStarted) {
                            loadStarted = true;
                            untrack(() => {
                                Promise.resolve().then(() => {
                                    if (!runtime.signal.aborted) return source.load?.(runtime);
                                }).catch(error => {
                                    if (!runtime.signal.aborted) this.fail(definition, error);
                                });
                            });
                        }
                    } catch (error) {
                        untrack(() => {
                            this.index.removeProvider(definition.id);
                            this.fail(definition, error);
                        });
                    }
                });
            });
            activation.stopItems = () => { stop(); unsubscribe?.(); };
        }
        this.changed();
    }

    private deactivateProvider(id: string): void {
        const activation = this.active.get(id);
        if (!activation) return;
        activation.controller.abort();
        activation.stopItems?.();
        this.active.delete(id);
        this.index.removeProvider(id);
        this.errors.delete(id);
        this.changed();
    }

    private retryStatic(id: string): void {
        const activation = this.active.get(id);
        if (!activation || activation.definition.kind !== 'static' || !this.errors.has(id)) return;
        const definition = activation.definition;
        this.deactivateProvider(id);
        this.activateProvider(definition);
    }

    private stopProviders(): void {
        for (const stop of this.monitors.values()) stop();
        this.monitors.clear();
        for (const id of [...this.active.keys()]) this.deactivateProvider(id);
        this.errors.clear();
        this.labels.clear();
    }

    private fail(provider: SearchProviderDefinition, error: unknown): void {
        this.errors.set(provider.id, {
            providerId: provider.id,
            groupId: provider.groupId,
            message: error instanceof Error ? error.message : String(error)
        });
        this.changed();
    }

    private changed(): void {
        this.revision += 1;
        this.manager?.invalidate();
    }

    private buildScopeOptions(app: HawkiApp): SearchScopeOptions {
        const plugins = new Map<string, {id: string; label: string}>();
        const modules = new Map<string, {id: string; pluginId: string; label: string}>();
        for (const {definition} of this.active.values()) {
            const module = app.modules.get(definition.moduleId);
            plugins.set(definition.pluginId, {id: definition.pluginId, label: definition.pluginId});
            modules.set(definition.moduleId, {
                id: definition.moduleId,
                pluginId: definition.pluginId,
                label: module.title?.(app.translator.translate, app.localization.locale) ?? module.name
            });
        }
        return {plugins: [...plugins.values()], modules: [...modules.values()]};
    }
}

function isReadable(value: readonly SearchEntry[] | Readable<readonly SearchEntry[]>): value is Readable<readonly SearchEntry[]> {
    return !Array.isArray(value) && typeof (value as Readable<readonly SearchEntry[]>)?.subscribe === 'function';
}

function snapshotEntries(items: readonly SearchEntry[]): SearchEntry[] {
    return items.map(item => ({
        id: item.id,
        entityKey: item.entityKey,
        title: item.title,
        description: item.description,
        content: item.content,
        keywords: item.keywords ? [...item.keywords] : undefined,
        icon: item.icon,
        onSelect: item.onSelect
    }));
}
