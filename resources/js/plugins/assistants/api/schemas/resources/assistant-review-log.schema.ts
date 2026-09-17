import z from 'zod';
import { WireUserSchema } from '../wireFragments';

/** Mirrors `App\Services\Assistant\Values\AssistantReviewStatus` on the backend. */
export const AssistantReviewActionSchema = z.enum(['approved', 'denied', 'blocked']);
export type AssistantReviewAction = z.infer<typeof AssistantReviewActionSchema>;

export interface AssistantReviewLog {
    id: string;
    action: AssistantReviewAction;
    reason: string | null;
    adminName: string;
    createdAt: string;
}

/**
 * The `assistant-review-logs` JSON:API resource: one row per approve/deny/block
 * decision (see `App\Models\Assistants\AssistantReviewLog`). Admin-only —
 * fetched by the Publishing Center's assistant detail page, never shown to a
 * creator.
 */
const AssistantReviewLogResourceSchema = z.object({
    id: z.string(),
    action: AssistantReviewActionSchema,
    reason: z.string().nullable(),
    created_at: z.string(),
    admin: WireUserSchema.nullable().optional()
});

const AssistantReviewLogSchema = AssistantReviewLogResourceSchema.transform((wire): AssistantReviewLog => ({
    id: wire.id,
    action: wire.action,
    reason: wire.reason,
    adminName: wire.admin?.display_name ?? '',
    createdAt: wire.created_at
}));

export default AssistantReviewLogSchema;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'assistant-review-logs': AssistantReviewLog;
    }
}
