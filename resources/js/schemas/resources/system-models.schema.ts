import z from 'zod';

export const WellKnownSystemModelTypes = ['default', 'title_generation', 'prompt_improvement', 'summary'] as const;
export type WellKnownSystemModelType = (typeof WellKnownSystemModelTypes)[number];

const SystemModelsSchema = z.object({
    id: z.string(),
    model_type: z.enum(WellKnownSystemModelTypes).or(z.string()),
    usage_type: z.string(),
    // Provider-qualified (`<provider_id>/<model_id>`), null when the linked model is not visible to the user
    model_id: z.string().nullable()
}).strict();

export default SystemModelsSchema;

export type SystemModel = z.infer<typeof SystemModelsSchema>

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'system-models': SystemModel;
    }
}
