import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import type {AiToolCapability} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import {__} from '$lib/utils/translator.js';

type ExtendedAiTool = AiTool & {
    isAvailableFor(model: AiModel, withOffline?: boolean): boolean;
    readonly displayName: string;
}

type AiToolCapabilityWrapper = {
    is_capability: true;
    icon_path: AiToolCapability['icon_path'];
    hasNativeCapabilityFor(model: AiModel): boolean;
    getTools(): ExtendedAiTool[];
    getToolsFor(model: AiModel): ExtendedAiTool[];
} & ExtendedAiTool;

type AiToolWrapper = AiTool & {
    is_capability: false;
} & ExtendedAiTool;

export type AiToolOrCapability = AiToolWrapper | AiToolCapabilityWrapper;

export function combineToolsAndCapabilities(tools: AiTool[], capabilities: AiToolCapability[]): AiToolOrCapability[] {
    const capabilityToolList = createCapabilityToolList();

    const capabilityMap: Record<string, AiToolCapabilityWrapper> = {};
    for (const capability of capabilities) {
        capabilityMap[capability.id] = createToolCapabilityWrapper(capability, capabilityToolList);
    }

    const list: AiToolOrCapability[] = [];
    for (const tool of tools) {
        const wrappedTool = createToolWrapper(tool);
        const toolCapabilityKey = tool.capability_key;
        if (toolCapabilityKey && capabilityMap[toolCapabilityKey]) {
            capabilityToolList.add(toolCapabilityKey, wrappedTool);
        } else {
            list.push(wrappedTool);
        }
    }

    return [...list, ...Object.values(capabilityMap)];
}

function humanizeName(name: string) {
    return name
        .replace(/([a-z])([A-Z])/g, '$1 $2') // Add space before capital letters
        .replace(/[-_]+/g, ' ') // Replace dashes and underscores with space
        .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize first letter of each word
}

function modelHasTool(model: AiModel, tool: AiTool): boolean {
    return model.tool_ids.includes(parseInt(tool.id));
}

function createToolCapabilityWrapper(capability: AiToolCapability, list: CapabilityToolList): AiToolCapabilityWrapper {
    function getTools() {
        return list.get(capability.id);
    }

    function getToolsFor(model: AiModel) {
        return getTools().filter(tool => modelHasTool(model, tool));
    }

    function getNonOfflineToolsFor(model: AiModel): AiTool[] {
        return getToolsFor(model).filter(tool => tool.status !== 'offline');
    }

    function hasNativeCapabilityFor(model: AiModel): boolean {
        if (model.settings?.native_capabilities === false) {
            return false;
        }
        return model.native_capabilities?.includes(capability.id) ?? false;
    }

    /**
     * This function gets extended in {@link createToolOrCapabilityWithState} to take into account the selected tool for a capability.
     * Otherwise, we can only check if the capability is available for the model by checking if the model has the native capability
     * or if it has any tools for the capability.
     */
    function isAvailableFor(model: AiModel, withOffline?: boolean): boolean {
        if (model.settings?.tool_calling !== true) {
            return false;
        }
        return hasNativeCapabilityFor(model) || (withOffline ? getToolsFor(model) : getNonOfflineToolsFor(model)).length > 0;
    }

    const nowString = new Date().toISOString();

    return {
        is_capability: true,
        id: capability.id,
        get name() {
            return `capability:${capability.id}`;
        },
        get displayName() {
            return __(capability.title_label) || humanizeName(capability.id);
        },
        get description() {
            if (capability.description_label) {
                return __(capability.description_label) || humanizeName(capability.id);
            }
            return humanizeName(capability.id);
        },
        getTools,
        isAvailableFor,
        getToolsFor,
        hasNativeCapabilityFor,
        status: 'online',
        capability_key: capability.id,
        icon_path: capability.icon_path,
        created_at: nowString,
        updated_at: nowString
    };
}

function createToolWrapper(tool: AiTool): AiToolWrapper {
    function isAvailableFor(model: AiModel, withOffline?: boolean): boolean {
        if ((withOffline !== true && tool.status === 'offline') || model.settings?.tool_calling === false) {
            return false;
        }
        return modelHasTool(model, tool);
    }

    return {
        ...tool,
        get displayName() {
            return humanizeName(tool.name);
        },
        get description() {
            return tool.description;
        },
        isAvailableFor,
        is_capability: false
    };
}

function createCapabilityToolList() {
    const toolsById = new Map<string, ExtendedAiTool[]>();

    const get = (id: string): ExtendedAiTool[] => toolsById.has(id) ? toolsById.get(id)! : [];
    const add = (id: string, tool: ExtendedAiTool): void => {
        const list = get(id);
        list.push(tool);
        toolsById.set(id, list);
    };

    return {
        get,
        add
    };
}

type CapabilityToolList = ReturnType<typeof createCapabilityToolList>;
