import z from 'zod';

/**
 * Validates the `ai-providers` API resource — the upstream AI vendor (e.g. OpenAI, Anthropic,
 * a self-hosted provider) that backs an `ai-models` entry. Embedded on `AiModel.provider` (see
 * `ai-models.schema.ts`) to show provider info in the model picker; not typically fetched as its
 * own collection.
 *
 * Registers the resource under the key `'ai-providers'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
const AiProvidersSchema = z.object({
    /** The provider's stable identifier, distinct from any single model's `model_id`. */
    provider_id: z.string(),
    name: z.string(),
    created_at: z.string(),
    updated_at: z.string()
});

export type AiProviderSchema = z.infer<typeof AiProvidersSchema>;

export default AiProvidersSchema;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-providers': AiProviderSchema;
    }
}
