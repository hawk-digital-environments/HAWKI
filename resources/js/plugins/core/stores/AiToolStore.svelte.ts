import {combineToolsAndCapabilities} from '$plugins/core/stores/aiToolStoreData.js';
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {AiTool} from '$plugins/core/schemas/resources/ai-tools.schema.js';
import type {AiToolCapability} from '$plugins/core/schemas/resources/ai-tools-capabilities.schema.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'ai-tools': AiToolStore;
    }
}

/**
 * Reactive store for AI tools and their associated capability definitions.
 *
 * Tools are concrete callable integrations (e.g. web-search, code-interpreter). Capabilities
 * are broader feature flags that may span multiple tools; a capability entry additionally
 * exposes `getTools()`/`getToolsFor(model)` (its underlying tools) and `hasNativeCapabilityFor(model)`
 * (whether the model supports it without any tool). Use each entry's `isAvailableFor(model)` to
 * check support against a specific model — don't read `tool_ids` off the model object directly.
 *
 * Access via `useStore('ai-tools')`.
 *
 * @example
 * // List the tools/capabilities available for the current model
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const aiToolStore = useStore('ai-tools');
 * const availableTools = $derived(aiToolStore.tools.filter(t => t.isAvailableFor(currentModel)));
 */
export class AiToolStore implements DataStore {
    public readonly name = 'ai-tools';

    private _app = $state.raw<HawkiApp | null>(null);
    private _tools = $state<AiTool[]>([]);
    private _capabilities = $state<AiToolCapability[]>([]);

    public tools = $derived.by(() => this._app === null ? [] : combineToolsAndCapabilities(
        this._app!.localization.translator,
        this._tools,
        this._capabilities
    ));

    public authorizationState = $state<'unknown' | 'refreshing' | 'ready'>('unknown');
    private generation = 0;
    public sessionGeneration = $state(0);

    public get authorizationRefreshing(): boolean {
        return this._app?.authorizationRefreshing ?? false;
    }

    public clear(): void {
        this.generation++;
        this.sessionGeneration++;
        this._tools = [];
        this._capabilities = [];
        this.authorizationState = 'unknown';
    }

    public ready(app: HawkiApp): void {
        this._app = app;
        app.events.sync.on('sessionLost', () => this.clear());
        app.events.async.on('logout', () => this.clear());
        app.events.async.on('connectionChanged', () => this.clear());
        app.events.async.on('connectionRefreshStarted', () => {
            this.generation++;
            this.authorizationState = 'refreshing';
        });
        // Model assignments and tool rules must come from the same completed refresh.
        app.events.async.on('connectionRefreshed', async () => {
            const generation = this.generation;
            try {
                await app.stores.get('ai-models').loadData(app);
                if (generation !== this.generation) return;
                await this.loadData(app);
            } catch (error) {
                if (generation === this.generation) this.authorizationState = 'unknown';
                console.error('Could not refresh authorized tool catalogs.', error);
            }
        });
    }

    public async loadData(app: HawkiApp): Promise<void> {
        this._app = app;
        if (!app.connection.isAuthenticated) {
            this.clear();
            return;
        }
        const actor = app.connection.userinfo.id;
        const generation = ++this.generation;
        this.authorizationState = 'refreshing';
        try {
            const [tools, capabilities] = await Promise.all([
                app.restApi.getResourceCollection('ai-tools', {query: {include: 'server', filter: {assigned: 1}}}),
                app.restApi.getResourceCollection('ai-tool-capabilities')
            ]);
            if (generation !== this.generation || !app.connection.isAuthenticated || app.connection.userinfo.id !== actor) return;
            this._tools = tools;
            this._capabilities = capabilities;
            this.authorizationState = 'ready';
        } catch (error) {
            if (generation === this.generation) this.authorizationState = 'unknown';
            throw error;
        }
    }
}
