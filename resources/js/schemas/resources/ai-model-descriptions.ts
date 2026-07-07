import z from 'zod';

export const AiModelDescriptionsSchema = z.object({
    id: z.string(),
    ai_model_id: z.number(),
    locale: z.string(),
    description: z.string().nullable()
});

export default AiModelDescriptionsSchema;

export type AiModelDescription = z.infer<typeof AiModelDescriptionsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-model-descriptions': AiModelDescription;
    }
}
