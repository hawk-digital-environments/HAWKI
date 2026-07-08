import {getResourceCollectionFromApi} from '$lib/data/api/api.js';
import {getConnection} from '$lib/data/connection/connection.js';
import {type AiToolOrCapability, combineToolsAndCapabilities} from '$lib/stores/aiToolStoreData.js';


/**
 * Reactive store for AI tools and their associated capability definitions.
 *
 * Tools are concrete callable integrations (e.g. web-search, code-interpreter). Capabilities
 * are broader feature flags that may span multiple tools. Use the `isAvailable*` and
 * `availableFor*` helpers to check support against a specific model — don't read
 * `tool_ids` from the model object directly.
 *
 * Populated by {@link loadAiToolsAndCapabilities} during bootstrap.
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
export class AiToolStore {
    /** All registered AI tools. Populated after {@link loadAiToolsAndCapabilities} resolves. */
    public tools = $state<AiToolOrCapability[]>([]);
}

export const aiToolStore = new AiToolStore();

/**
 * Fetches all AI tools and capability definitions from the API and populates
 * {@link aiToolStore}. Called during bootstrap.
 */
export async function loadAiToolsAndCapabilities() {
    if (getConnection().type !== 'internal_authenticated') {
        return;
    }

    const [tools, capabilities] = await Promise.all([
        getResourceCollectionFromApi('ai-tools', {query: {include: 'server', filter: {assigned: 1}}}),
        getResourceCollectionFromApi('ai-tool-capabilities')
    ]);

    aiToolStore.tools = combineToolsAndCapabilities(tools, capabilities);
}
