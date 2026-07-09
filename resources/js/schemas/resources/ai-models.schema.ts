import z from 'zod';
import AiProvidersSchema from '$lib/schemas/resources/ai-providers.schema.js';
import {wellKnownAiToolCapabilities} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';
import {wellKnownAiModelFlags} from '$lib/schemas/resources/ai-model-flags.js';

export const wellKnownAiModelTypes = ['chat', 'image_generation', 'video_generation'] as const;
export type WellKnownAiModelType = typeof wellKnownAiModelTypes[number];

export const wellKnownAiModelParameters = ['temperature', 'top_p', 'max_tokens', 'max_thinking_tokens'] as const;
export type WellKnownAiModelParameter = typeof wellKnownAiModelParameters[number];

export const wellKnownAiModelIoMethods = ['text', 'image', 'audio', 'video', 'code'] as const;
export type WellKnownAiModelIoMethod = typeof wellKnownAiModelIoMethods[number];

export const wellKnownAiModelSettings = ['max_tool_calling_rounds_streaming', 'max_tool_calling_rounds', 'file_upload', 'tool_calling', 'native_capabilities'] as const;
export type WellKnownAiModelSetting = typeof wellKnownAiModelSettings[number];

const BaseAiModelSchema = z.object({
    id: z.string(),
    active: z.boolean(),
    model_id: z.string(),
    model_type: z.union([z.enum(wellKnownAiModelTypes), z.string()]).nullable(),
    label: z.string(),
    input: z.array(z.union([z.enum(wellKnownAiModelIoMethods), z.string()])),
    output: z.array(z.union([z.enum(wellKnownAiModelIoMethods), z.string()])),
    parameters: z.record(z.union([z.enum(wellKnownAiModelParameters), z.string()]), z.unknown()).nullable(),
    status: z.enum(['online', 'offline', 'unknown']),
    demand: z.enum(['low', 'medium', 'high']),
    native_capabilities: z.array(z.union([z.enum(wellKnownAiToolCapabilities), z.string()])).nullable(),
    settings: z.record(z.union([z.enum(wellKnownAiModelSettings), z.string()]), z.unknown()).nullable(),
    provider: AiProvidersSchema.optional(),
    flags: z.array(z.union([z.enum(wellKnownAiModelFlags), z.string()])).nullable(),
    tool_ids: z.array(z.number()),
    created_at: z.string(),
    updated_at: z.string()
});

const ChatAiModelPaidPricingRangeSchema = z.object({
    currency: z.string(),
    input_cost_per_token: z.number(),
    input_cost_per_cached_token: z.number(),
    output_cost_per_token: z.number(),
    output_cost_per_reasoning_token: z.number().nullable(),
    range: z.tuple([z.number(), z.number().nullable()])
});

const ChatAiModelSchema = BaseAiModelSchema.extend({
    model_type: z.literal('chat'),
    limits: z.object({
        max_input_tokens: z.number().nullable(),
        max_output_tokens: z.number().nullable()
    }).nullable(),
    pricing: z.union([
        z.object({
            is_free: z.boolean()
        }),
        z.object({
            ranges: z.array(ChatAiModelPaidPricingRangeSchema).nullable(),
            priority_ranges: z.array(ChatAiModelPaidPricingRangeSchema).nullable()
        })
    ]).nullable()
});

const UnknownAiModelSchema = BaseAiModelSchema.extend({
    model_type: z.union([z.enum(wellKnownAiModelTypes), z.string()]).nullable(),
    limits: z.unknown().optional(),
    pricing: z.unknown().optional()
});

const AiModelsSchema = z.union([
    ChatAiModelSchema,
    UnknownAiModelSchema // catch-all; must be last
]);

export default AiModelsSchema;

export type AiModel = z.infer<typeof AiModelsSchema>;
export type AiModelStatusType = AiModel['status'];
export type AiModelDemandType = AiModel['demand'];
export type AiModelLimitsType = AiModel['limits'];
export type AiModelPricingType = AiModel['pricing'];
export type AiModelParameterKeyType = keyof AiModel['parameters'] | string;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-models': AiModel;
    }
}
