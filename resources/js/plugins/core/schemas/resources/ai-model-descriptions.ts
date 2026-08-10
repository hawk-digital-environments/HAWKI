import z from 'zod';

/**
 * Validates the `ai-model-descriptions` API resource — the localized marketing/help text shown
 * for an `ai-models` entry (e.g. in the model picker's detail view), separate from the model's
 * technical config so it can be translated per locale without touching `ai-models`.
 *
 * Registers the resource under the key `'ai-model-descriptions'` in `HawkiResourceSchemas` (see
 * the `declare module` augmentation below).
 */
export const AiModelDescriptionsSchema = z.object({
    id: z.string(),
    /** The `ai-models` resource this description belongs to (numeric backend id, joined against `AiModel.id`). */
    ai_model_id: z.number(),
    /** BCP-47-ish locale code (e.g. `en`, `de`) this description is written in. */
    locale: z.string(),
    /** The description text; `null` if no description has been authored for this model/locale yet. */
    description: z.string().nullable()
});

export default AiModelDescriptionsSchema;

export type AiModelDescription = z.infer<typeof AiModelDescriptionsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-model-descriptions': AiModelDescription;
    }
}
