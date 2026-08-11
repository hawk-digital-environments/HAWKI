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
 * are broader feature flags that may span multiple tools. Use the `isAvailable*` and
 * `availableFor*` helpers to check support against a specific model — don't read
 * `tool_ids` from the model object directly.
 *
 * @example
 * // Show only the tools the active model supports
 * import {aiToolStore} from '$lib/stores/AiToolStore.svelte.js';
 * const tools = $derived(aiToolStore.availableToolsForModel(chatInputStore.currentModel!));
 *
 * @example
 * // Check whether a specific named tool is available before adding it to a request
 * if (aiToolStore.isAvailableToolOfModel('web-search', currentModel)) { ... }
 */
export class AiToolStore implements DataStore {
    public readonly name = 'ai-tools';

    private _app: HawkiApp | null = null;
    private _tools = $state<AiTool[]>([]);
    private _capabilities = $state<AiToolCapability[]>([]);

    public tools = $derived.by(() => combineToolsAndCapabilities(
        this._app!.localization.translator,
        this._tools,
        this._capabilities
    ));

    public async loadData(app: HawkiApp) {
        try {
            app.authenticatedConnection;
        } catch {
            return;
        }

        const [tools, capabilities] = await Promise.all([
            app.restApi.getResourceCollection('ai-tools', {query: {include: 'server', filter: {assigned: 1}}}),
            app.restApi.getResourceCollection('ai-tool-capabilities')
        ]);

        this._app = app;
        this._tools = tools;
        this._capabilities = capabilities;
    }
}
