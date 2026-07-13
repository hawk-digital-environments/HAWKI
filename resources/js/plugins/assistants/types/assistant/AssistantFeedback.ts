import z from 'zod';

/**
 * A user-submitted comment about an assistant, listed in the feedback section
 * of the detail page.
 */
export const AssistantFeedbackSchema = z.object({
    id: z.string(),
    text: z.string(),
    /** Display name of the submitter, already resolved server-side. */
    author: z.string(),
    createdAt: z.string()
});

export type AssistantFeedback = z.infer<typeof AssistantFeedbackSchema>;
