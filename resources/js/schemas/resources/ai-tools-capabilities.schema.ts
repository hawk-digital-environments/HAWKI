import z from 'zod';

export const AiToolCapabilityValues = ['yes', 'no', 'native', 'tool'] as const;

const AiToolCapabilitiesSchema = z.object({
    id: z.string(),
    default_value: z.enum(AiToolCapabilityValues),
    title_label: z.string(),
    description_label: z.string().nullable(),
    icon_path: z.string()
}).strict();

export default AiToolCapabilitiesSchema;

export type AiToolCapability = z.infer<typeof AiToolCapabilitiesSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-tool-capabilities': AiToolCapability;
    }
}
