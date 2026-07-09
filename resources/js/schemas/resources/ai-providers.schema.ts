import z from 'zod';

const AiProvidersSchema = z.object({
    provider_id: z.string(),
    name: z.string(),
    created_at: z.string(),
    updated_at: z.string()
});

export type AiProviderSchema = z.infer<typeof AiProvidersSchema>;

export default AiProvidersSchema;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-providers': AiProviderSchema;
    }
}
