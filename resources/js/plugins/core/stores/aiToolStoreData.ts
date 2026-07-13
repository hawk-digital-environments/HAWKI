import type {AiTool} from '$plugins/core/schemas/resources/ai-tools.schema.js';
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
import type {AiToolCapability} from '$plugins/core/schemas/resources/ai-tools-capabilities.schema.js';
import type {Translator} from '$lib/kernel/localization/translator.js';

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

/**
 * Merges raw tools and capabilities into one flat list for display, wrapping each in
 * `isAvailableFor`/`displayName`/`description` helpers. A tool whose `capability_key` matches a
 * known capability is folded into that capability's `getTools()`/`getToolsFor()` instead of
 * appearing as its own top-level entry — the returned list only contains standalone tools plus
 * one wrapper per capability.
 */
export function combineToolsAndCapabilities(
    translator: Translator,
    tools: AiTool[],
    capabilities: AiToolCapability[]
): AiToolOrCapability[] {
    const capabilityToolList = createCapabilityToolList();

    const capabilityMap: Record<string, AiToolCapabilityWrapper> = {};
    for (const capability of capabilities) {
        capabilityMap[capability.id] = createToolCapabilityWrapper(translator, capability, capabilityToolList);
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

/**
 * Whether `model` can actually use `tool` — offline tools are unusable unless
 * `withOffline` is passed, a model with tool-calling switched off can't use
 * any tool, and otherwise the model must have the tool in its `tool_ids`.
 *
 * This is what {@link AiToolWrapper.isAvailableFor} delegates to; exported
 * standalone so callers that only have a plain {@link AiTool} (not the
 * `AiToolOrCapability` wrapper — e.g. after a round-trip through
 * `JSON.stringify`, which drops methods) can run the same check. See the
 * assistant builder's `model.svelte` / `ModelToolConflictPanel.svelte` for
 * such a caller: `Assistant.aiTools` is persisted to `sessionStorage`, so it
 * can't be relied on to still carry the wrapper's methods.
 */
export function isAiToolAvailableFor(tool: AiTool, model: AiModel, withOffline?: boolean): boolean {
    if ((withOffline !== true && tool.status === 'offline') || model.settings?.tool_calling === false) {
        return false;
    }
    return modelHasTool(model, tool);
}

function createToolCapabilityWrapper(
    translator: Translator,
    capability: AiToolCapability,
    list: CapabilityToolList
): AiToolCapabilityWrapper {
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
            return translator.translate(capability.title_label) || humanizeName(capability.id);
        },
        get description() {
            if (capability.description_label) {
                return translator.translate(capability.description_label) || humanizeName(capability.id);
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
        return isAiToolAvailableFor(tool, model, withOffline);
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
