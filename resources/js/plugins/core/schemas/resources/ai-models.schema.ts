import z from 'zod';
import {wellKnownAiToolCapabilities} from '$plugins/core/schemas/resources/ai-tools-capabilities.schema.js';
import AiProvidersSchema from '$plugins/core/schemas/resources/ai-providers.schema.js';
import {wellKnownAiModelFlags} from '$plugins/core/schemas/resources/ai-model-flags.schema.js';
import AiModelDescriptionsSchema from '$plugins/core/schemas/resources/ai-model-descriptions.schema.js';

/**
 * Validates the `ai-models` API resource — every AI model (chat, image generation, video
 * generation, ...) configured on the backend, used to populate the model picker, resolve
 * pricing/limits, and check capability/flag support before sending a message.
 *
 * The resource is a discriminated union on `model_type`: `ChatAiModelSchema` gives strict
 * validation for `model_type: 'chat'` models (the only fully-modeled shape today, including
 * token limits and pricing tiers), while `UnknownAiModelSchema` is a permissive catch-all for
 * any other/future `model_type` (e.g. `image_generation`, `video_generation`, or a type added
 * by a different plugin) whose `limits`/`pricing` shape isn't known to this schema yet.
 * `UnknownAiModelSchema` MUST stay last in the union so `ChatAiModelSchema` gets first refusal.
 *
 * Registers the resource under the key `'ai-models'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
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
    /** Whether the model is enabled/selectable. Inactive models are hidden from pickers but may still appear in historical messages. */
    active: z.boolean(),
    /** The provider-side model identifier (e.g. `gpt-4o`), as opposed to `id` which is HAWKI's own resource id. */
    model_id: z.string(),
    model_type: z.union([z.enum(wellKnownAiModelTypes), z.string()]).nullable(),
    label: z.string(),
    /** Link to the vendor's documentation page for this model, if the backend knows one (shown on the model card). */
    documentation_url: z.string().nullable().optional(),
    /** Input modalities the model accepts, e.g. `['text', 'image']`. */
    input: z.array(z.union([z.enum(wellKnownAiModelIoMethods), z.string()])),
    /** Output modalities the model can produce. */
    output: z.array(z.union([z.enum(wellKnownAiModelIoMethods), z.string()])),
    /** Free-form map of tunable request parameters (e.g. `temperature`, `max_tokens`) to their current/default value; value shape depends on the parameter. */
    parameters: z.record(z.union([z.enum(wellKnownAiModelParameters), z.string()]), z.unknown()).nullable(),
    /** Live availability of the provider/model, used to render the status dot in the model picker. */
    status: z.enum(['online', 'offline', 'unknown']),
    /** Current load indicator for the model, used to render the demand bars in the model picker. */
    demand: z.enum(['low', 'medium', 'high']),
    /** Capabilities the model supports out of the box (without a tool), referencing {@link wellKnownAiToolCapabilities} ids. */
    native_capabilities: z.array(z.union([z.enum(wellKnownAiToolCapabilities), z.string()])).nullable(),
    /** Free-form map of behavioral/feature toggles (e.g. `file_upload`, `tool_calling`) to their value; keyed by {@link wellKnownAiModelSettings}. */
    settings: z.record(z.union([z.enum(wellKnownAiModelSettings), z.string()]), z.unknown()).nullable(),
    provider: AiProvidersSchema.optional(),
    /** Localized description texts (the `ai-model-descriptions` resource), present when fetched with `include=description`. Pick a locale via a helper like `ModelCard`'s — don't blindly take the first entry. */
    description: z.array(AiModelDescriptionsSchema).optional(),
    /** Descriptive badges shown in the UI (e.g. `open-weights`, `strength-code-generation`), referencing {@link wellKnownAiModelFlags} ids. */
    flags: z.array(z.union([z.enum(wellKnownAiModelFlags), z.string()])).nullable(),
    /** IDs of the `ai-tools` this model is linked/restricted to. */
    tool_ids: z.array(z.number()),
    created_at: z.string(),
    updated_at: z.string()
});

const ChatAiModelPaidPricingRangeSchema = z.object({
    currency: z.string(),
    input_cost_per_token: z.number(),
    input_cost_per_cached_token: z.number(),
    output_cost_per_token: z.number(),
    /** Cost per reasoning/thinking token, if the model bills those separately; `null` when not applicable. */
    output_cost_per_reasoning_token: z.number().nullable(),
    /** `[from, to]` token-count boundaries this pricing tier applies to; `to` is `null` for an open-ended top tier. */
    range: z.tuple([z.number(), z.number().nullable()])
});

const ChatAiModelSchema = BaseAiModelSchema.extend({
    model_type: z.literal('chat'),
    limits: z.object({
        max_input_tokens: z.number().nullable(),
        max_output_tokens: z.number().nullable()
    }).nullable(),
    /**
     * Either a flat `{free: true}` shape for models with no usage-based cost (matching the
     * backend serializer in `AiModelSchema.php`), or tiered pricing with `ranges` (standard)
     * and `priority_ranges` (e.g. low-latency/priority tier) broken down by token-count
     * bracket via {@link ChatAiModelPaidPricingRangeSchema}. `null` when the cost is unknown.
     */
    pricing: z.union([
        z.object({
            free: z.boolean()
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

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-models': AiModel;
    }
}
