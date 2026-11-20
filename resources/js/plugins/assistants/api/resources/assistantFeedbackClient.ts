import type { Assistant, AssistantFeedback } from "$plugins/assistants/types/assistant";
import { useApp } from "$lib/app/hooks/useApp.svelte";
import { logApiError } from "$plugins/assistants/api/errors";
import { feedbackToApi } from "$plugins/assistants/api/schemas/resources/assistant-feedback.schema";

const ASSISTANT_FEEDBACK = "assistant-feedback";

/**
 * `assistant-feedback` only registers `store` (see `routes/api.php`) — there
 * is no standalone index endpoint. Feedback for an assistant arrives inlined
 * on the assistant resource instead (`getAssistant(id, {include:
 * ['assistant_feedback', 'assistant_feedback.user']})` → `Assistant.feedbacks`,
 * mapped by `assistants.schema.ts`); read it from there rather than here.
 */
export async function submitAssistantFeedbacks(
    text: string,
    assistant: Assistant,
): Promise<AssistantFeedback> {
    try {
        const body = feedbackToApi(text, assistant);
        return await useApp().restApi.createResource(ASSISTANT_FEEDBACK, body.attributes, {
            relationships: body.relationships,
        });
    } catch (err) {
        throw logApiError("submitAssistantFeedbacks", err, { assistantId: assistant.id });
    }
}
