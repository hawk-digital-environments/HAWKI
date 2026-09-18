import { z } from 'zod';

/**
 * Derived publication status of an assistant, computed server-side by
 * `App\Services\Admin\Repositories\AssistantRepository`. Mirrors
 * `App\Services\Assistant\Values\AssistantReviewStatus`'s two denial
 * variants: Ask for edit → requires_revision (`needs_revision`, the creator
 * may revise and resubmit), Deny → denied (permanent, an admin must clear it
 * before a resubmission is possible). Accept → published/private.
 */
export const AdminAssistantStatusSchema = z.enum([
    'waiting_for_review',
    'published',
    'private',
    'requires_revision',
    'denied'
]);
export type AdminAssistantStatus = z.infer<typeof AdminAssistantStatusSchema>;

/** Row of the `admin-assistants` resource (Publishing Center). Read-only: no create/edit form. */
export const AdminAssistantSchema = z.object({
    id: z.string(),
    name: z.string().nullable(),
    handle: z.string().nullable(),
    creator: z.string().nullable(),
    status: AdminAssistantStatusSchema,
    version: z.string().nullable(),
    /** Name of the assistant this one was remixed from; `null` for originals. */
    based_on: z.string().nullable(),
    created_at: z.string().nullable(),
    updated_at: z.string().nullable(),
    /** An admin has added flags to this (still waiting-for-review) assistant but hasn't sent, discarded, or acted on them yet. */
    is_draft: z.boolean()
});
export default AdminAssistantSchema;
export type AdminAssistantResource = z.infer<typeof AdminAssistantSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-assistants': AdminAssistantResource;
    }
}
