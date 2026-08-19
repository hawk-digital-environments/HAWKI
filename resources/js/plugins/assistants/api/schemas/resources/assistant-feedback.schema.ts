import z from 'zod';
import type { Assistant, AssistantFeedback } from '$plugins/assistants/types/assistant';
import { WireUserSchema } from '../wireFragments';

/**
 * The `assistant-feedback` JSON:API resource, as it arrives from the backend
 * (see `AssistantFeedbackSchema::fields()` in
 * `app/JsonApi/V1/AssistantFeedback/AssistantFeedbackSchema.php`).
 *
 * `author` isn't a wire field: it's `user.display_name`, only present when
 * the request includes `user` — see {@link submitAssistantFeedbacks} /
 * `getAssistantFeedbacks` in `assistantFeedbackClient.ts`.
 */
const AssistantFeedbackResourceSchema = z.object({
    id: z.string(),
    text: z.string(),
    created_at: z.string(),
    user: WireUserSchema.nullable().optional()
});

/**
 * Registered by `AssistantsPlugin.resourceSchemas()` for
 * `RestApi.getResource(Collection)`. Feedback that arrives inlined on an
 * assistant (`include=assistant_feedback`) is already mapped by
 * `assistants.schema.ts`'s own `WireFeedbackSchema`; this is for the
 * standalone `assistant-feedback` endpoint.
 */
const AssistantFeedbackSchema = AssistantFeedbackResourceSchema.transform((wire): AssistantFeedback => ({
    id: wire.id,
    text: wire.text,
    author: wire.user?.display_name ?? '',
    createdAt: wire.created_at
}));

export default AssistantFeedbackSchema;

/** The **write** half: object → JSON:API request body for `POST assistant-feedback`. */
export function feedbackToApi(text: string, assistant: Assistant) {
    return {
        type: 'assistant-feedback',
        attributes: { text },
        relationships: {
            assistant: {
                data: {
                    type: 'assistants',
                    id: assistant.id
                }
            }
        }
    };
}
