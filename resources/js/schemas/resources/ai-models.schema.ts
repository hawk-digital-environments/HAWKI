import z from 'zod';
import AiProvidersSchema from '$lib/schemas/resources/ai-providers.schema.js';
import {AiToolCapabilityValues} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';

export const wellKnownAiModelParameters = ['temperature', 'top_p', 'max_tokens', 'max_thinking_tokens'] as const;
export type WellKnownAiModelParameter = typeof wellKnownAiModelParameters[number];

const AiModelsSchema = z.object({
    id: z.string(),
    model_id: z.string(),
    label: z.string(),
    input: z.array(z.string()),
    output: z.array(z.string()),
    parameters: z.record(z.union([z.enum(wellKnownAiModelParameters), z.string()]), z.unknown()).nullable(),
    status: z.enum(['online', 'offline', 'unknown']),
    demand: z.enum(['low', 'medium', 'high']),
    capabilities: z.record(z.string(), z.enum(AiToolCapabilityValues)).nullable(),
    settings: z.record(z.string(), z.unknown()).nullable(),
    provider: AiProvidersSchema.optional(),
    tool_ids: z.array(z.number()),
    created_at: z.string(),
    updated_at: z.string()
});

export default AiModelsSchema;

export type AiModel = z.infer<typeof AiModelsSchema>;
export type AiModelStatusType = AiModel['status'];
export type AiModelDemandType = AiModel['demand'];
export type AiModelParameterKeyType = keyof AiModel['parameters'] | string;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-models': AiModel;
    }
}
