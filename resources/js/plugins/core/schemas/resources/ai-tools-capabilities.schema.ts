import z from 'zod';

/**
 * Validates the `ai-tool-capabilities` API resource — the catalog of capability "badges"
 * that can be attached to an {@link import('./ai-tools.schema.js').AiTool} (via `capability_key`)
 * or listed in an {@link import('./ai-models.schema.js').AiModel}'s `native_capabilities` array
 * (e.g. "this tool/model can do web search").
 *
 * Each entry describes one capability with a label, an optional description, and an icon to
 * render in the UI (tool menu, model picker, etc.).
 *
 * Registers the resource under the key `'ai-tool-capabilities'` in `HawkiResourceSchemas`
 * (see the `declare module` augmentation below), which makes `restApi.getResourceCollection('ai-tool-capabilities')`
 * and friends return a validated, typed `AiToolCapability[]`.
 */
export const wellKnownAiToolCapabilities = [
    'web_search',
    'knowledge_base',
    'web_fetch',
    'code_execution',
    'tool_calling'
] as const;

export type WellKnownAiToolCapability = typeof wellKnownAiToolCapabilities[number];

const AiToolCapabilitiesSchema = z.object({
    /** Capability identifier. One of {@link wellKnownAiToolCapabilities} for built-in capabilities, or an arbitrary string for capabilities added by other plugins/backends. */
    id: z.union([z.string(), z.enum(wellKnownAiToolCapabilities)]),
    title_label: z.string(),
    description_label: z.string().nullable(),
    /** Path/URL to the icon representing this capability in the UI. */
    icon_path: z.string()
}).strict();

export default AiToolCapabilitiesSchema;

export type AiToolCapability = z.infer<typeof AiToolCapabilitiesSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-tool-capabilities': AiToolCapability;
    }
}
