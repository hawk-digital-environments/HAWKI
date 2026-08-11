import z from 'zod';

/**
 * Validates the `system-prompts` API resource — the (admin-configurable) system prompt texts
 * HAWKI sends to the AI on the backend for a given purpose (default chat behavior, summarization,
 * title generation, prompt improvement, ...), per locale. Consumed by `SystemPromptStore.svelte.ts`
 * and edited via `SystemPromptDialog.svelte`.
 *
 * Registers the resource under the key `'system-prompts'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
export const WellKnownSystemPromptTypes = ['default', 'summary', 'title_generation', 'prompt_improvement'] as const;
export type WellKnownSystemPromptType = (typeof WellKnownSystemPromptTypes)[number];

const SystemPromptsSchema = z.object({
    id: z.string(),
    /** What the prompt is used for, e.g. one of {@link WellKnownSystemPromptTypes}. Stored as a plain string (not an enum) so the backend can introduce new types without a frontend schema change. */
    prompt_type: z.string(),
    /**
     * The deployment/integration context this prompt applies to (e.g. the main HAWKI app vs. an
     * embedding external app), distinct from `prompt_type` (which purpose the prompt serves).
     * Together with `prompt_type` and `locale` it forms the unique key for a prompt on the backend
     * (see `App\Services\Ai\SystemPrompts\SystemPromptRepository`). Non-default usage types overlay
     * the default usage type's prompts, only overriding what they explicitly define.
     */
    usage_type: z.string(),
    /** BCP-47-ish locale code (e.g. `en`, `de`) this prompt text applies to. */
    locale: z.string(),
    /** The actual system prompt text sent to the AI. */
    prompt: z.string()
}).strict();

export default SystemPromptsSchema;

export type SystemPrompt = z.infer<typeof SystemPromptsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'system-prompts': SystemPrompt;
    }
}
