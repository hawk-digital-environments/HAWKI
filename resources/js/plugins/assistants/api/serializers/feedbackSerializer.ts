import type {AssistantFeedback} from "$plugins/assistants/types/assistant";

/**
 * Map an `assistant-feedback` resource onto {@link AssistantFeedback}.
 *
 * Note the wire keys are snake_case: `jsona` (see `JsonaPropertyMapper`) does no
 * case conversion, so this reads `display_name` / `created_at`, not the
 * camelCase names the old SvelteKit deserializer produced.
 *
 * Feedback that arrives inlined on an assistant (`include=assistant_feedback`)
 * is already mapped by `assistants.schema.ts`; this is for the standalone
 * feedback endpoint.
 */
export function feedbackFromApi(data: any): AssistantFeedback {
    return {
        id: String(data.id),
        author: data.user?.display_name ?? "",
        text: data.text,
        createdAt: data.created_at
    };
}
