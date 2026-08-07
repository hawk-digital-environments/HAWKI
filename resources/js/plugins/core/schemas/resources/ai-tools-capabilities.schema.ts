import z from 'zod';

export const wellKnownAiToolCapabilities = [
    'web_search',
    'knowledge_base',
    'web_fetch',
    'code_execution',
    'tool_calling'
] as const;

export type WellKnownAiToolCapability = typeof wellKnownAiToolCapabilities[number];

const AiToolCapabilitiesSchema = z.object({
    id: z.union([z.string(), z.enum(wellKnownAiToolCapabilities)]),
    title_label: z.string(),
    description_label: z.string().nullable(),
    icon_path: z.string()
}).strict();

export default AiToolCapabilitiesSchema;

export type AiToolCapability = z.infer<typeof AiToolCapabilitiesSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-tool-capabilities': AiToolCapability;
    }
}
