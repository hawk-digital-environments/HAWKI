import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import {getResourceCollectionFromApi} from '$lib/data/api/api.js';
import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import type {AiToolCapability} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';
import {getConnection} from '$lib/data/connection/connection.js';

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
    public tools = $state<AiTool[]>([]);

    public getOneByName(name: string): AiTool | null {
        const tool = this.tools.find(t => t.name === name);
        if (!tool) {
            return null;
        }
        return tool;
    }

    public isAvailableForModel(tool: AiTool | string, model: AiModel): boolean {
        if (typeof tool === 'string') {
            tool = this.tools.find(t => t.name === tool) as AiTool;
            if (!tool) {
                console.warn(`Tool with name ${tool} not found in store.`);
                return false;
            }
        }

        return model.tool_ids.includes(parseInt(tool.id));
    };

    public areAllToolsAvailableForModel(tools: (AiTool | string)[], model: AiModel): boolean {
        return tools.every(tool => this.isAvailableForModel(tool, model));
    };

    public availableToolsForModel(model: AiModel): AiTool[] {
        return this.tools.filter(tool => this.isAvailableForModel(tool, model));
    };

    /** All registered capability definitions. Populated after {@link loadAiToolsAndCapabilities} resolves. */
    public capabilities = $state<AiToolCapability[]>([]);

    public isAvailableCapabilityOfModel(capability: AiToolCapability | string, model: AiModel): boolean {
        if (typeof capability === 'string') {
            capability = this.capabilities.find(c => c.id === capability) as AiToolCapability;
            if (!capability) {
                console.warn(`Capability with id ${capability} not found in store.`);
                return false;
            }
        }

        const modelCapability = model.capabilities ? model.capabilities[capability.id] : null;
        const defaultCapability = capability.default_value;
        const capabilityValue = modelCapability ?? defaultCapability;

        return capabilityValue !== 'no';
    };

    public areAllCapabilitiesAvailableForModel(capabilities: (AiToolCapability | string)[], model: AiModel): boolean {
        return capabilities.every(capability => this.isAvailableCapabilityOfModel(capability, model));
    };

    public availableCapabilitiesForModel(model: AiModel): AiToolCapability[] {
        return this.capabilities.filter(capability => this.isAvailableCapabilityOfModel(capability, model));
    };

    private capabilityByToolName = $derived.by(() => {
        const map: Record<string, AiToolCapability> = {};
        for (const tool of this.tools) {
            if (!tool.capability_key) {
                continue;
            }
            const capability = this.capabilities.find(c => c.id === tool.capability_key);
            if (!capability) {
                continue;
            }
            map[tool.name] = capability;
        }
        return map;
    });

    public getCapabilityForTool(tool: AiTool | string): AiToolCapability | null {
        if (typeof tool === 'string') {
            return this.capabilityByToolName[tool] ?? null;
        }
        return this.capabilityByToolName[tool.name] ?? null;
    }
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

    aiToolStore.tools = tools;
    aiToolStore.capabilities = capabilities;
}
