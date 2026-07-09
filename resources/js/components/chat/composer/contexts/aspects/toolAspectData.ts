import type {AiToolOrCapability} from '$lib/stores/aiToolStoreData.js';
import type {AiToolStore} from '$lib/stores/AiToolStore.svelte.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';

const rootToolSymbol = Symbol('rootTool');

export type AiToolOrCapabilityWithState = {
    /**
     * A special symbol holding the original tool or capability object that this state object wraps.
     */
    readonly [rootToolSymbol]: AiToolOrCapability;

    /**
     * IF {@link AiToolOrCapabilityWithState.is_capability} is true,
     * this value determines which tool should be used to fulfill the capability.
     * Either the custom tool to use, or 'native' to use the model's native capability, or 'auto' to let the backend decide.
     * If {@link AiToolOrCapabilityWithState.is_capability} is false, this value is ignored.
     */
    toolSelection?: AiTool | 'native' | 'auto';
    /**
     * Additional settings for the tool, if any. This is a free-form object that will be passed to the tool's API call.
     * The structure of this object is tool-specific and should be documented in the tool's schema.
     * If {@link this.is_capability} is true, this value is used for the selected tool to fulfill the capability.
     * If it is false, this value is used for the tool itself.
     */
    toolSettings?: Record<string, any>;
    /**
     * The transfer string represents the combination of the selected tool + optional capability + optional settings in a single string.
     * The format is
     * - for a tool: "$tool_name" (this is the legacy format, so we are backwards compatible with it)
     * - for a tool with settings : "$tool_name:$settings_json"
     * - for a capability: "capability:$capability_name:$tool_name" where $tool_name is either the name of the selected tool, or 'native' or 'auto'.
     * - for a capability with settings: "capability:$capability_name:$tool_name:$settings_json"
     * With this string we can tell the backend exactly what was configured and even restore it later from a string.
     */
    toTransferString(): string;
} & AiToolOrCapability;

function isToolOrCapabilityWithState(obj: any): obj is AiToolOrCapabilityWithState {
    return obj && typeof obj.toTransferString === 'function' && obj[rootToolSymbol] !== undefined;
}

/**
 * Helper to avoid wrapping a tool or capability multiple times in a state object.
 * This will unwrap the tool or capability if it is already wrapped in a state object, and return the original tool or capability.
 * @param tool
 */
function getRootTool(tool: AiToolOrCapability | AiToolOrCapabilityWithState): AiToolOrCapability {
    while (isToolOrCapabilityWithState(tool)) {
        tool = tool[rootToolSymbol];
    }
    return tool;
}

export function createToolOrCapabilityWithState(
    tool: AiToolOrCapability,
    toolSelection?: AiToolOrCapabilityWithState['toolSelection'],
    toolSettings?: AiToolOrCapabilityWithState['toolSettings']
): AiToolOrCapabilityWithState {
    tool = getRootTool(tool);

    toolSelection = toolSelection ?? (tool.is_capability ? 'auto' : undefined);

    function toTransferString(): string {
        const parts = [tool.name]; // capabilities are already prefixed with "capability:" in their name, so we don't need to add it here.
        if (tool.is_capability) {
            if (toolSelection === 'native' || toolSelection === 'auto') {
                parts.push(toolSelection);
            } else {
                parts.push(toolSelection!.name);
            }
        }
        if (toolSettings) {
            parts.push(JSON.stringify(toolSettings));
        }
        return parts.join(':');
    }

    /**
     * This extends the `isAvailableFor` method of the tool to take into account the selected tool for a capability.
     * If the tool is a capability and a tool is selected, it will check if the selected tool is available for the model.
     * Otherwise, it will check if the capability itself is available for the model.
     */
    function extendedIsAvailableFor(model: AiModel, withOffline?: boolean): boolean {
        // We execute the root method first, to include checks if the model supports tool calling and alike.
        // Then we can still check if the selected tool is available for the model, if any.
        if (!tool.isAvailableFor(model, withOffline)) {
            return false;
        }

        if (tool.is_capability && toolSelection) {
            const hasNativeCapability = tool.hasNativeCapabilityFor(model);
            if (toolSelection === 'native') {
                return hasNativeCapability;
            }
            if (toolSelection === 'auto') {
                return hasNativeCapability || tool.getToolsFor(model).length > 0;
            }
            return model.tool_ids.includes(Number(toolSelection.id)) && (withOffline || toolSelection.status !== 'offline');
        }

        return true;
    }

    return {
        ...tool,
        [rootToolSymbol]: tool,
        get displayName() {
            if (tool.is_capability && toolSelection) {
                if (toolSelection === 'native') {
                    return `${tool.displayName} (native)`;
                }
                if (toolSelection === 'auto') {
                    return `${tool.displayName} (auto)`;
                }
                return `${tool.displayName} (${(toolSelection as AiToolOrCapability).displayName})`;
            }
            return tool.displayName;
        },
        get status() {
            if (tool.is_capability && toolSelection) {
                if (toolSelection === 'native') {
                    return 'online';
                }
                if (toolSelection === 'auto') {
                    // This is flawed, because it will return 'online' even if the model doesn't have the online tools available,
                    // Or we return offline, even if the model would have a native capability available.
                    // But in this context, we don't have access to the model, so we can't check.
                    return tool.getTools().some(t => t.status === 'online') ? 'online' : 'offline';
                }
                return toolSelection.status;
            }
            return tool.status;
        },
        isAvailableFor: extendedIsAvailableFor,
        toolSelection,
        toolSettings,
        toTransferString
    };
}

function findToolByName(
    name: string,
    toolStore: AiToolStore
): { tool: AiToolOrCapability, innerTool?: AiTool | 'native' | 'auto' } | null {
    for (const t of toolStore.tools) {
        if (t.is_capability) {
            for (const capabilityTool of t.getTools()) {
                if (capabilityTool.name === name) {
                    return {tool: t, innerTool: capabilityTool};
                }
            }
        } else if (t.name === name) {
            return {tool: t};
        }
    }
    return null;
}

function findCapabilityByName(
    name: string,
    toolStore: AiToolStore
): AiToolOrCapability | null {
    for (const t of toolStore.tools) {
        if (!t.is_capability) {
            continue;
        }
        if (t.name === name || t.name === `capability:${name}`) {
            return t;
        }
    }
    return null;
}

export function createToolOrCapabilityWithStateFromTransferString(
    transferString: string,
    toolStore: AiToolStore
): AiToolOrCapabilityWithState | null {
    let resolvedTool: AiToolOrCapability | null = null;
    let resolvedInnerTool: AiTool | 'native' | 'auto' | undefined = undefined;
    let resolvedSettingsString: string | undefined = undefined;
    let toolSettings: AiToolOrCapabilityWithState['toolSettings'] | undefined = undefined;

    const parts = transferString.split(':');
    const firstPart = parts[0];
    if (firstPart === 'capability') {
        const capabilityName = parts[1];
        const toolName = parts[2];
        resolvedSettingsString = parts.slice(3).join(':');
        resolvedTool = findCapabilityByName(capabilityName, toolStore);
        if (!resolvedTool || !resolvedTool.is_capability) {
            return null;
        }
        if (toolName === 'native' || toolName === 'auto') {
            resolvedInnerTool = toolName;
        } else {
            resolvedInnerTool = resolvedTool.getTools().find(t => t.name === toolName);
        }
    } else {
        const toolName = firstPart;
        resolvedSettingsString = parts.slice(1).join(':');
        const found = findToolByName(toolName, toolStore);
        if (!found) {
            return null;
        }
        resolvedTool = found.tool;
        resolvedInnerTool = found.innerTool;
    }

    try {
        if (resolvedSettingsString) {
            toolSettings = JSON.parse(resolvedSettingsString);
        }
    } catch (e) {
        console.error('Failed to parse transfer string:', transferString, e);
        return null;
    }

    return createToolOrCapabilityWithState(resolvedTool, resolvedInnerTool, toolSettings);
}
