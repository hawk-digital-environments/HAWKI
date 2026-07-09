import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import {__} from '$lib/utils/translator.js';
import type {AiToolCapability} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';

/**
 * Helpers for rendering AI tool metadata in the UI.
 *
 * Both functions resolve the tool's linked capability first and pull the
 * translated label from there. They fall back to a humanised version of the
 * raw tool name when no capability or translation is found, so they are always
 * safe to call without null-checks.
 *
 * @example
 * import {toolDisplayName, toolDisplayDescription} from '$lib/utils/aiToolUtils.js';
 * <span>{toolDisplayName(tool)}</span>
 * {#if toolDisplayDescription(tool)}<p>{toolDisplayDescription(tool)}</p>{/if}
 */

function humanizeName(name: string) {
    return name
        .replace(/([a-z])([A-Z])/g, '$1 $2') // Add space before capital letters
        .replace(/[-_]+/g, ' ') // Replace dashes and underscores with space
        .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize first letter of each word
}

export function capabilityDisplayName(capability: AiToolCapability): string {
    return __(capability.title_label) || humanizeName(capability.id);
}

export function toolDisplayName(tool: AiTool): string {
    return humanizeName(tool.name);
}

export function capabilityDisplayDescription(capability: AiToolCapability): string {
    if (capability.description_label) {
        return __(capability.description_label) || humanizeName(capability.id);
    }
    return humanizeName(capability.id);
}

export function toolDisplayDescription(tool: AiTool): string | null {
    if (tool.description) {
        return tool.description;
    }
    return null;
}
