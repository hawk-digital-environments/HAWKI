import z from 'zod';

/**
 * Validates the `system-models` API resource — the (admin-configurable) assignment of which
 * `ai-models` entry is used by the backend for a given internal purpose (default chat model,
 * title generation, prompt improvement, summarization, ...). This is distinct from the
 * user-facing model picker: these are models HAWKI itself calls for its own operations.
 *
 * Registers the resource under the key `'system-models'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
export const WellKnownSystemModelTypes = ['default', 'title_generation', 'prompt_improvement', 'summary'] as const;
export type WellKnownSystemModelType = (typeof WellKnownSystemModelTypes)[number];

const SystemModelsSchema = z.object({
    id: z.string(),
    /** Which internal operation this assignment is for, e.g. one of {@link WellKnownSystemModelTypes}. Stored as a plain string (not an enum) so the backend can introduce new types without a frontend schema change. */
    model_type: z.enum(WellKnownSystemModelTypes).or(z.string()),
    /** The deployment/integration context this assignment applies to (e.g. the main HAWKI app vs. an embedding external app); see the analogous field on `system-prompts.schema.ts` for details. Together with `model_type` it forms the unique key for an assignment. */
    usage_type: z.string(),
    /** The `ai-models` resource id (`AiModel.id`) assigned to this purpose. */
    model_id: z.string()
}).strict();

export default SystemModelsSchema;

export type SystemModel = z.infer<typeof SystemModelsSchema>

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'system-models': SystemModel;
    }
}
